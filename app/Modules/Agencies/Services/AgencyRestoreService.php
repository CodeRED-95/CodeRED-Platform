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

            // Contexto para sanear claves foráneas: el backup puede venir de otra
            // instalación, donde los ids de usuario/ubigeo del archivo no existen.
            // Sin esto la inserción viola agencies_created_by_foreign (y similares).
            $fkContext = $this->buildForeignKeyContext($restore, $agencies);

            $idMap = [];
            $created = 0;
            $updated = 0;
            $processed = 0;
            $deferredMoves = [];

            foreach (array_chunk($agencies, self::CHUNK) as $chunk) {
                DB::transaction(function () use ($chunk, $columns, $fkContext, &$idMap, &$created, &$updated, &$deferredMoves): void {
                    foreach ($chunk as $row) {
                        $result = $this->restoreAgencyRow($row, $columns, $fkContext);
                        $idMap[$result['backup_id']] = $result['id'];
                        $result['created'] ? $created++ : $updated++;

                        if ($result['moved_to_backup_id'] !== null) {
                            // Se guarda el id de destino del ARCHIVO; se resuelve
                            // después por code (portable entre instalaciones).
                            $deferredMoves[$result['id']] = $result['moved_to_backup_id'];
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
            $movedLinked = $this->applyDeferredMoves($deferredMoves, $idMap, $fkContext['backup_code_by_id']);

            $restore->forceFill(['stage' => 'Restaurando historial de nombres', 'progress' => 88])->save();
            $historyCount = $this->restoreNameHistories($archive['agency_name_histories'], $idMap, $fkContext);

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
     * Prepara el contexto para sanear claves foráneas de un archivo que puede
     * proceder de otra instalación.
     *
     * @param  array<int, array<string, mixed>>  $agencies
     * @return array{
     *     valid_user_ids: array<int, bool>,
     *     valid_ubigeo_ids: array<int, bool>,
     *     fallback_user_id: int|null,
     *     backup_code_by_id: array<int, string>
     * }
     */
    private function buildForeignKeyContext(AgencyBackupRestore $restore, array $agencies): array
    {
        $validUserIds = array_fill_keys(
            DB::table('users')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            true
        );

        $validUbigeoIds = [];
        if (Schema::hasTable('ubigeos')) {
            $validUbigeoIds = array_fill_keys(
                DB::table('ubigeos')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                true
            );
        }

        // Usuario que ejecuta la restauración: primera opción para reemplazar un
        // created_by/updated_by histórico que ya no existe. Debe existir él mismo.
        $fallbackUserId = $restore->created_by !== null && isset($validUserIds[(int) $restore->created_by])
            ? (int) $restore->created_by
            : null;

        // Mapa id-del-archivo => code, para resolver moved_to_agency_id por un
        // identificador estable en lugar del id autoincremental.
        $codeById = [];
        foreach ($agencies as $agency) {
            $id = (int) ($agency['id'] ?? 0);
            $code = isset($agency['code']) && $agency['code'] !== '' ? (string) $agency['code'] : null;
            if ($id > 0 && $code !== null) {
                $codeById[$id] = $code;
            }
        }

        return [
            'valid_user_ids' => $validUserIds,
            'valid_ubigeo_ids' => $validUbigeoIds,
            'fallback_user_id' => $fallbackUserId,
            'backup_code_by_id' => $codeById,
        ];
    }

    /**
     * Resuelve una FK hacia users: conserva el id si existe, si no usa el admin
     * que ejecuta la restauración y, en última instancia, null (columna nullable).
     *
     * @param  array{valid_user_ids: array<int, bool>, fallback_user_id: int|null}  $context
     */
    private function resolveUserFk(mixed $value, array $context): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        if (isset($context['valid_user_ids'][$id])) {
            return $id;
        }

        return $context['fallback_user_id'];
    }

    /**
     * Resuelve ubigeo_id: se conserva si existe en el destino; si no, null. No
     * hay un sustituto razonable para un ubigeo inexistente.
     *
     * @param  array{valid_ubigeo_ids: array<int, bool>}  $context
     */
    private function resolveUbigeoFk(mixed $value, array $context): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return isset($context['valid_ubigeo_ids'][$id]) ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $fkContext
     * @return array{id: int, backup_id: int, created: bool, moved_to_backup_id: int|null}
     */
    private function restoreAgencyRow(array $row, array $columns, array $fkContext): array
    {
        $backupId = (int) ($row['id'] ?? 0);
        $attributes = array_intersect_key($row, array_flip($columns));

        // moved_to_agency_id se aplica en una segunda pasada, cuando ya existen
        // todas las agencias; se guarda el id de destino del ARCHIVO.
        $movedTo = isset($attributes['moved_to_agency_id']) && $attributes['moved_to_agency_id'] !== null
            ? (int) $attributes['moved_to_agency_id']
            : null;
        $attributes['moved_to_agency_id'] = null;
        unset($attributes['id']);

        // Saneo de FKs: nunca se inserta un id que no exista en el destino.
        if (array_key_exists('created_by', $attributes)) {
            $attributes['created_by'] = $this->resolveUserFk($attributes['created_by'], $fkContext);
        }
        if (array_key_exists('updated_by', $attributes)) {
            $attributes['updated_by'] = $this->resolveUserFk($attributes['updated_by'], $fkContext);
        }
        if (array_key_exists('ubigeo_id', $attributes)) {
            $attributes['ubigeo_id'] = $this->resolveUbigeoFk($attributes['ubigeo_id'], $fkContext);
        }

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

            return ['id' => (int) $existingId, 'backup_id' => $backupId, 'created' => false, 'moved_to_backup_id' => $movedTo];
        }

        $newId = DB::table('agencies')->insertGetId($attributes);

        return ['id' => (int) $newId, 'backup_id' => $backupId, 'created' => true, 'moved_to_backup_id' => $movedTo];
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
     * Enlaza moved_to_agency_id en una segunda pasada, de forma portable.
     *
     * El destino se resuelve así, sin confiar nunca en el id autoincremental:
     *   1. por el mapa archivo→real (la agencia destino venía en la copia);
     *   2. si no, por el `code` estable del destino, buscándolo en el destino.
     * Si no se puede resolver, se deja en null: se conservan igualmente
     * has_moved, moved_to_address y move_notice (los datos textuales del
     * traslado no se pierden).
     *
     * @param  array<int, int>  $deferredMoves  id real => id de destino en el archivo
     * @param  array<int, int>  $idMap  id del archivo => id real
     * @param  array<int, string>  $backupCodeById  id del archivo => code
     */
    private function applyDeferredMoves(array $deferredMoves, array $idMap, array $backupCodeById): int
    {
        $applied = 0;

        foreach ($deferredMoves as $agencyId => $backupTargetId) {
            $targetId = $idMap[$backupTargetId] ?? null;

            // Reconstrucción por identificador estable (code), no por id.
            if ($targetId === null && isset($backupCodeById[$backupTargetId])) {
                $targetId = DB::table('agencies')->where('code', $backupCodeById[$backupTargetId])->value('id');
                $targetId = $targetId !== null ? (int) $targetId : null;
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
     * @param  array<string, mixed>  $fkContext
     */
    private function restoreNameHistories(array $histories, array $idMap, array $fkContext): int
    {
        if ($histories === []) {
            return 0;
        }

        $columns = Schema::getColumnListing('agency_name_histories');
        $restoredAgencyIds = array_values($idMap);
        $inserted = 0;

        DB::transaction(function () use ($histories, $columns, $restoredAgencyIds, $fkContext, &$inserted): void {
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

                    // changed_by → users(id): mismo saneo de FK que en agencies.
                    if (array_key_exists('changed_by', $row)) {
                        $row['changed_by'] = $this->resolveUserFk($row['changed_by'], $fkContext);
                    }

                    // import_run_id no tiene FK en el esquema; se conserva tal cual.

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
