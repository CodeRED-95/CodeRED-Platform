<?php

declare(strict_types=1);

namespace App\Modules\Agencies\Services;

use App\Core\Audit\AuditLogger;
use App\Modules\Agencies\Enums\AgencyRestoreStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyBackupRestore;
use App\Modules\Agencies\Support\AgencyVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Restaura una copia de agencias dejando cada registro exactamente como estaba.
 *
 * Decisiones que conviene conocer antes de tocar esto:
 *
 * - **Se escribe por consulta directa, no con Eloquent.** El modelo Agency
 *   regenera `slug`, `map_url` y `place` al guardar, y el observador crea
 *   historial de nombres. Guardando con el modelo, una restauración
 *   sobrescribiría justo los valores que intenta recuperar.
 * - **Pero el feed de sincronización sí se alimenta.** Al saltarse el
 *   observador habría que renunciar a `agency_sync_changes`, del que dependen
 *   los consumidores incrementales, así que se registra explícitamente al
 *   final (ver `recordSyncChanges`).
 * - **La clave de emparejamiento es `code`,** no el id: al restaurar sobre una
 *   base donde las agencias se recrearon, los ids no coinciden pero el código
 *   sí. Los ids del archivo se traducen mediante un mapa que después se usa
 *   para `moved_to_agency_id` y para el historial de nombres.
 * - **Nunca se borra definitivamente.** El modo `replace` solo envía a la
 *   papelera lo que no aparece en la copia.
 */
class AgencyRestoreService
{
    /** Filas por lote. Cada lote va en su propia transacción y actualiza el progreso. */
    private const CHUNK = 200;

    /** Tope defensivo para el archivo, en bytes (200 MB). */
    private const MAX_FILE_BYTES = 209715200;

    public function __construct(
        private AuditLogger $audit,
        private AgencyBackupService $backups,
        private AgencySyncChangeRecorder $syncChanges,
    ) {}

    /**
     * Comprueba que el archivo es una copia de agencias de CodeRED y devuelve
     * sus metadatos. Se usa tanto al subir (validación temprana) como al
     * restaurar.
     *
     * @return array{metadata: array<string, mixed>, agencies: array<int, array<string, mixed>>, agency_name_histories: array<int, array<string, mixed>>}
     */
    public function readArchive(string $disk, string $path): array
    {
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            throw new RuntimeException('El archivo de copia no existe o ya no está disponible.');
        }

        $size = (int) $filesystem->size($path);
        if ($size > self::MAX_FILE_BYTES) {
            throw new RuntimeException('El archivo supera el tamaño máximo admitido de 200 MB.');
        }

        $raw = $filesystem->get($path);
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('El archivo no contiene JSON válido.');
        }

        $metadata = is_array($decoded['metadata'] ?? null) ? $decoded['metadata'] : [];
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

        if (($metadata['module'] ?? null) !== 'agencies' || ($metadata['type'] ?? null) !== 'agency-backup') {
            throw new RuntimeException('El archivo no es una copia de seguridad de agencias de CodeRED Platform.');
        }

        if (! is_array($data['agencies'] ?? null)) {
            throw new RuntimeException('La copia no contiene la sección de agencias.');
        }

        $histories = is_array($data['agency_name_histories'] ?? null) ? $data['agency_name_histories'] : [];

        return [
            'metadata' => $metadata,
            'agencies' => array_values(array_filter($data['agencies'], 'is_array')),
            'agency_name_histories' => array_values(array_filter($histories, 'is_array')),
        ];
    }

    /**
     * Ejecuta la restauración completa. La invoca RestoreAgencyBackupJob.
     */
    public function restore(AgencyBackupRestore $restore): AgencyBackupRestore
    {
        $restore->forceFill([
            'status' => AgencyRestoreStatus::Processing,
            'stage' => 'Leyendo archivo',
            'progress' => 1,
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $archive = $this->readArchive($restore->disk, $restore->path);
            $agencies = $archive['agencies'];
            $total = count($agencies);

            if ($total === 0) {
                throw new RuntimeException('La copia no contiene agencias que restaurar.');
            }

            $restore->forceFill([
                'total_records' => $total,
                'stage' => 'Creando copia de seguridad previa',
                'progress' => 3,
            ])->save();

            // Red de seguridad: si la restauración era la equivocada, el estado
            // anterior queda guardado antes de escribir nada.
            $safety = $this->backups->create($restore->created_by, $restore->disk, 'codered-agencies-pre-restore-'.now()->format('Y-m-d-His').'.json', false);
            $restore->forceFill(['safety_backup_id' => $safety->id, 'stage' => 'Restaurando agencias', 'progress' => 8])->save();

            $columns = $this->restorableColumns();
            $idMap = [];
            $created = 0;
            $updated = 0;
            $processed = 0;
            $deferredMoves = [];

            foreach (array_chunk($agencies, self::CHUNK) as $chunk) {
                DB::transaction(function () use ($chunk, $columns, &$idMap, &$created, &$updated, &$deferredMoves): void {
                    foreach ($chunk as $row) {
                        $result = $this->restoreAgencyRow($row, $columns);
                        $idMap[$result['backup_id']] = $result['id'];
                        $result['created'] ? $created++ : $updated++;

                        if ($result['moved_to_agency_id'] !== null) {
                            $deferredMoves[$result['id']] = $result['moved_to_agency_id'];
                        }
                    }
                });

                $processed += count($chunk);
                $restore->forceFill([
                    'processed_records' => $processed,
                    'created_records' => $created,
                    'updated_records' => $updated,
                    // Se reserva el tramo 8-80 para las agencias; el resto es
                    // para traslados, historial y papelera.
                    'progress' => (int) min(80, 8 + (int) round(($processed / max(1, $total)) * 72)),
                ])->save();
            }

            // Segunda pasada: la autorreferencia moved_to_agency_id solo puede
            // resolverse cuando ya existen todas las agencias.
            $restore->forceFill(['stage' => 'Enlazando traslados', 'progress' => 84])->save();
            $movedLinked = $this->applyDeferredMoves($deferredMoves, $idMap);

            $restore->forceFill(['stage' => 'Restaurando historial de nombres', 'progress' => 88])->save();
            $historyCount = $this->restoreNameHistories($archive['agency_name_histories'], $idMap);

            $trashed = 0;
            if ($restore->mode === AgencyBackupRestore::MODE_REPLACE) {
                $restore->forceFill(['stage' => 'Enviando a papelera lo que no está en la copia', 'progress' => 92])->save();
                $trashed = $this->trashMissingAgencies(array_values($idMap));
            }

            $restore->forceFill(['stage' => 'Actualizando feed de sincronización', 'progress' => 96])->save();
            $this->syncSequence();
            $this->recordSyncChanges(array_values($idMap));
            AgencyVersion::bump();

            $summary = [
                'schema_version' => $archive['metadata']['schema_version'] ?? 1,
                'backup_created_at' => $archive['metadata']['created_at'] ?? null,
                'agencies_in_file' => $total,
                'created' => $created,
                'updated' => $updated,
                'moved_links_restored' => $movedLinked,
                'name_histories' => $historyCount,
                'trashed' => $trashed,
                'mode' => $restore->mode,
            ];

            $restore->forceFill([
                'status' => AgencyRestoreStatus::Completed,
                'stage' => 'Completada',
                'progress' => 100,
                'processed_records' => $processed,
                'created_records' => $created,
                'updated_records' => $updated,
                'trashed_records' => $trashed,
                'name_histories_restored' => $historyCount,
                'summary' => $summary,
                'finished_at' => now(),
            ])->save();

            $this->audit->log($restore, 'agency_backup_restored', [], [
                'filename' => $restore->filename,
                'mode' => $restore->mode,
                'created' => $created,
                'updated' => $updated,
                'trashed' => $trashed,
            ], ['filename', 'mode', 'created', 'updated', 'trashed']);

            return $restore->refresh();
        } catch (Throwable $exception) {
            $restore->forceFill([
                'status' => AgencyRestoreStatus::Failed,
                'stage' => 'Fallida',
                'error_message' => $this->presentableError($exception),
                'finished_at' => now(),
            ])->save();

            report($exception);

            throw $exception;
        }
    }

    /**
     * Columnas que se pueden restaurar: las que existen realmente en la tabla.
     * Si la copia trae columnas retiradas del esquema, se descartan en lugar de
     * reventar la restauración.
     *
     * @return array<int, string>
     */
    private function restorableColumns(): array
    {
        return Schema::getColumnListing('agencies');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $columns
     * @return array{id: int, backup_id: int, created: bool, moved_to_agency_id: int|null}
     */
    private function restoreAgencyRow(array $row, array $columns): array
    {
        $backupId = (int) ($row['id'] ?? 0);
        $attributes = array_intersect_key($row, array_flip($columns));

        // moved_to_agency_id se aplica en una segunda pasada, cuando ya existen
        // todas las agencias y se conoce el mapa de ids.
        $movedTo = isset($attributes['moved_to_agency_id']) && $attributes['moved_to_agency_id'] !== null
            ? (int) $attributes['moved_to_agency_id']
            : null;
        $attributes['moved_to_agency_id'] = null;
        unset($attributes['id']);

        $attributes = $this->encodeJsonColumns($attributes);

        $code = isset($row['code']) && $row['code'] !== '' ? (string) $row['code'] : null;
        $existingId = null;

        if ($code !== null) {
            $existingId = DB::table('agencies')->where('code', $code)->value('id');
        }

        if ($existingId === null && $backupId > 0) {
            $byId = DB::table('agencies')->where('id', $backupId)->first(['id', 'code']);
            // Solo se reutiliza el id del archivo si está libre o si pertenece
            // a la misma agencia; si no, se inserta con id nuevo y se remapea.
            if ($byId !== null && ($code === null || $byId->code === $code)) {
                $existingId = (int) $byId->id;
            } elseif ($byId === null) {
                $attributes['id'] = $backupId;
            }
        }

        if ($existingId !== null) {
            DB::table('agencies')->where('id', $existingId)->update($attributes);

            return ['id' => (int) $existingId, 'backup_id' => $backupId, 'created' => false, 'moved_to_agency_id' => $movedTo];
        }

        $newId = DB::table('agencies')->insertGetId($attributes);

        return ['id' => (int) $newId, 'backup_id' => $backupId, 'created' => true, 'moved_to_agency_id' => $movedTo];
    }

    /**
     * Las columnas jsonb necesitan llegar como texto JSON; en el archivo vienen
     * ya decodificadas a array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function encodeJsonColumns(array $attributes): array
    {
        foreach (['services'] as $column) {
            if (array_key_exists($column, $attributes) && is_array($attributes[$column])) {
                $attributes[$column] = json_encode($attributes[$column], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $attributes;
    }

    /**
     * @param  array<int, int>  $deferredMoves  id real => id de destino en el archivo
     * @param  array<int, int>  $idMap  id del archivo => id real
     */
    private function applyDeferredMoves(array $deferredMoves, array $idMap): int
    {
        $applied = 0;

        foreach ($deferredMoves as $agencyId => $backupTargetId) {
            // Si el destino venía en la copia se traduce; si no, se acepta tal
            // cual solo cuando esa agencia existe en la base.
            $targetId = $idMap[$backupTargetId] ?? null;

            if ($targetId === null && DB::table('agencies')->where('id', $backupTargetId)->exists()) {
                $targetId = $backupTargetId;
            }

            if ($targetId === null) {
                continue;
            }

            DB::table('agencies')->where('id', $agencyId)->update(['moved_to_agency_id' => $targetId]);
            $applied++;
        }

        return $applied;
    }

    /**
     * @param  array<int, array<string, mixed>>  $histories
     * @param  array<int, int>  $idMap
     */
    private function restoreNameHistories(array $histories, array $idMap): int
    {
        if ($histories === []) {
            return 0;
        }

        $columns = Schema::getColumnListing('agency_name_histories');
        $restoredAgencyIds = array_values($idMap);
        $inserted = 0;

        DB::transaction(function () use ($histories, $columns, $restoredAgencyIds, &$inserted): void {
            // Se reemplaza el historial de las agencias restauradas para que el
            // resultado sea el del archivo y no una mezcla con lo que hubiera.
            foreach (array_chunk($restoredAgencyIds, 500) as $chunk) {
                DB::table('agency_name_histories')->whereIn('agency_id', $chunk)->delete();
            }

            foreach (array_chunk($histories, self::CHUNK) as $chunk) {
                $rows = [];

                foreach ($chunk as $history) {
                    $backupAgencyId = (int) ($history['agency_id'] ?? 0);
                    $agencyId = $idMap[$backupAgencyId] ?? null;

                    if ($agencyId === null) {
                        continue;
                    }

                    $row = array_intersect_key($history, array_flip($columns));
                    unset($row['id']);
                    $row['agency_id'] = $agencyId;

                    if (array_key_exists('metadata', $row) && is_array($row['metadata'])) {
                        $row['metadata'] = json_encode($row['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    $rows[] = $row;
                }

                if ($rows !== []) {
                    DB::table('agency_name_histories')->insert($rows);
                    $inserted += count($rows);
                }
            }
        });

        return $inserted;
    }

    /**
     * Modo réplica exacta: lo que no está en la copia va a la papelera. Nunca
     * se elimina definitivamente, de modo que siempre es reversible.
     *
     * @param  array<int, int>  $restoredIds
     */
    private function trashMissingAgencies(array $restoredIds): int
    {
        $query = DB::table('agencies')->whereNull('deleted_at');

        if ($restoredIds !== []) {
            $query->whereNotIn('id', $restoredIds);
        }

        $ids = $query->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table('agencies')->whereIn('id', $chunk)->update(['deleted_at' => now(), 'updated_at' => now()]);
        }

        foreach (Agency::onlyTrashed()->whereIn('id', $ids)->cursor() as $agency) {
            $this->syncChanges->delete($agency);
        }

        return count($ids);
    }

    /**
     * Al insertar con id explícito, la secuencia de PostgreSQL se queda atrás y
     * el siguiente insert normal chocaría con la clave primaria.
     */
    private function syncSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('agencies', 'id'), GREATEST((SELECT COALESCE(MAX(id), 1) FROM agencies), 1))");
        DB::statement("SELECT setval(pg_get_serial_sequence('agency_name_histories', 'id'), GREATEST((SELECT COALESCE(MAX(id), 1) FROM agency_name_histories), 1))");
    }

    /**
     * Alimenta agency_sync_changes para los registros restaurados. Sin esto los
     * consumidores incrementales no verían la restauración, porque se escribió
     * saltándose el observador del modelo.
     *
     * @param  array<int, int>  $ids
     */
    private function recordSyncChanges(array $ids): void
    {
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (Agency::withTrashed()->whereIn('id', $chunk)->cursor() as $agency) {
                $agency->deleted_at !== null
                    ? $this->syncChanges->delete($agency)
                    : $this->syncChanges->upsert($agency);
            }
        }
    }

    private function presentableError(Throwable $exception): string
    {
        // RuntimeException lleva mensajes redactados para el operador; el resto
        // puede exponer detalles internos, así que se resume.
        return $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'No fue posible completar la restauración. Revisa los registros del sistema para el detalle técnico.';
    }
}
