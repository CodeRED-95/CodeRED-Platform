<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Services;

use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupUpload;
use App\Modules\Ruc\Models\RucBackupUploadPart;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Recibe backups RUC divididos en partes por packages/ruc-tools
 * (manifest.json + *.partNNNN) y los ensambla en un RucBackup normal.
 *
 * Cada parte llega en un request HTTP separado — esto NO reconstruye nada
 * en el navegador ni acepta un archivo ya unido; el ensamblado ocurre
 * siempre en el servidor, por streaming, cuando la última parte válida
 * llega. Ver packages/ruc-tools/src/Services/{BackupPartitioner,
 * ManifestService}.php para el formato exacto que este servicio consume.
 */
class RucBackupMultipartUploadService
{
    private const UPLOADS_DIR = 'backups/ruc/uploads'; // relativo al disco "local"

    private const SHA256_PATTERN = '/^[a-f0-9]{64}$/i';

    /** Nombre de archivo seguro: sin separadores de ruta, sin "..". */
    private const SAFE_FILENAME_PATTERN = '/^[A-Za-z0-9._-]+$/';

    public function __construct(private readonly RucBackupService $backupService)
    {
        if (! Storage::disk('local')->exists(self::UPLOADS_DIR)) {
            Storage::disk('local')->makeDirectory(self::UPLOADS_DIR);
        }
    }

    /**
     * Valida el manifest SIN confiar ciegamente en sus datos y crea la
     * sesión de subida. No escribe ninguna parte todavía.
     */
    public function createSession(array $manifest, User $user): RucBackupUpload
    {
        $this->assertManifestIsWellFormed($manifest);

        $totalSizeBytes = (int) $manifest['total_size_bytes'];
        $this->checkDiskSpace($totalSizeBytes);

        $uuid = (string) Str::uuid();
        $relativeDir = self::UPLOADS_DIR.'/'.$uuid;
        Storage::disk('local')->makeDirectory($relativeDir);

        $expiresHours = (int) config('ruc.backup.multipart.session_expires_hours', 24);

        return DB::transaction(function () use ($manifest, $user, $uuid, $relativeDir, $totalSizeBytes, $expiresHours): RucBackupUpload {
            $upload = RucBackupUpload::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'status' => RucBackupUpload::STATUS_PENDING,
                'original_filename' => basename((string) $manifest['original_filename']),
                'manifest_json' => $manifest,
                'total_size_bytes' => $totalSizeBytes,
                'part_size_bytes' => (int) $manifest['part_size_bytes'],
                'total_parts' => (int) $manifest['total_parts'],
                'received_parts' => 0,
                'received_bytes' => 0,
                'temporary_directory' => $relativeDir,
                'started_at' => now(),
                'expires_at' => now()->addHours($expiresHours),
            ]);

            foreach ($manifest['parts'] as $part) {
                RucBackupUploadPart::create([
                    'upload_id' => $upload->id,
                    'part_index' => (int) $part['index'],
                    'filename' => basename((string) $part['filename']),
                    'size_bytes' => (int) $part['size_bytes'],
                    'checksum_sha256' => strtolower((string) $part['sha256']),
                    'status' => RucBackupUploadPart::STATUS_PENDING,
                ]);
            }

            return $upload;
        });
    }

    /**
     * Valida estructura/límites del manifest. NUNCA confía en los valores
     * declarados por el cliente sin verificarlos contra límites del server.
     */
    private function assertManifestIsWellFormed(array $manifest): void
    {
        foreach (['format_version', 'backup_type', 'original_filename', 'total_records', 'total_size_bytes', 'part_size_bytes', 'total_parts', 'sha256', 'parts'] as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new \RuntimeException("Manifest inválido: falta la clave \"{$key}\".");
            }
        }

        $supportedVersions = config('ruc.backup.multipart.supported_format_versions', [1]);
        if (! in_array($manifest['format_version'], $supportedVersions, true)) {
            throw new \RuntimeException("Manifest inválido: format_version {$manifest['format_version']} no soportado.");
        }

        if ($manifest['backup_type'] !== 'ruc_records') {
            throw new \RuntimeException('Manifest inválido: backup_type debe ser "ruc_records".');
        }

        if (! preg_match(self::SAFE_FILENAME_PATTERN, basename((string) $manifest['original_filename']))
            || basename((string) $manifest['original_filename']) !== $manifest['original_filename']) {
            throw new \RuntimeException('Manifest inválido: original_filename contiene caracteres no permitidos o una ruta.');
        }

        $totalParts = $manifest['total_parts'];
        $maxParts = (int) config('ruc.backup.multipart.max_total_parts', 500);
        if (! is_int($totalParts) || $totalParts <= 0) {
            throw new \RuntimeException('Manifest inválido: total_parts debe ser un entero mayor que 0.');
        }
        if ($totalParts > $maxParts) {
            throw new \RuntimeException("Manifest inválido: total_parts ({$totalParts}) excede el máximo permitido ({$maxParts}).");
        }

        $totalSizeBytes = $manifest['total_size_bytes'];
        $maxTotalBytes = (int) config('ruc.backup.multipart.max_total_size_mb', 20000) * 1024 * 1024;
        if (! is_int($totalSizeBytes) || $totalSizeBytes <= 0) {
            throw new \RuntimeException('Manifest inválido: total_size_bytes debe ser un entero mayor que 0.');
        }
        if ($totalSizeBytes > $maxTotalBytes) {
            throw new \RuntimeException('Manifest inválido: total_size_bytes excede el máximo permitido en este servidor.');
        }

        $partSizeBytes = $manifest['part_size_bytes'];
        $maxPartBytes = (int) config('ruc.backup.multipart.max_part_size_mb', 95) * 1024 * 1024;
        if (! is_int($partSizeBytes) || $partSizeBytes <= 0) {
            throw new \RuntimeException('Manifest inválido: part_size_bytes debe ser un entero mayor que 0.');
        }
        if ($partSizeBytes > $maxPartBytes) {
            throw new \RuntimeException('Manifest inválido: part_size_bytes excede el máximo permitido en este servidor ('.($maxPartBytes / 1024 / 1024).' MB). Vuelve a generar el backup con --part-size menor.');
        }

        if (! is_string($manifest['sha256']) || ! preg_match(self::SHA256_PATTERN, $manifest['sha256'])) {
            throw new \RuntimeException('Manifest inválido: sha256 no tiene formato válido.');
        }

        if (! is_array($manifest['parts']) || count($manifest['parts']) !== $totalParts) {
            throw new \RuntimeException('Manifest inválido: la cantidad de partes no coincide con total_parts.');
        }

        $seenIndexes = [];
        foreach ($manifest['parts'] as $part) {
            foreach (['index', 'filename', 'size_bytes', 'sha256'] as $key) {
                if (! array_key_exists($key, $part)) {
                    throw new \RuntimeException("Manifest inválido: una parte no tiene la clave \"{$key}\".");
                }
            }
            $filename = basename((string) $part['filename']);
            if ($filename !== $part['filename'] || ! preg_match(self::SAFE_FILENAME_PATTERN, $filename)) {
                throw new \RuntimeException('Manifest inválido: el nombre de una parte contiene caracteres no permitidos o una ruta.');
            }
            if (! is_string($part['sha256']) || ! preg_match(self::SHA256_PATTERN, $part['sha256'])) {
                throw new \RuntimeException("Manifest inválido: checksum de {$filename} no tiene formato válido.");
            }
            if (! is_int($part['size_bytes']) || $part['size_bytes'] <= 0 || $part['size_bytes'] > $partSizeBytes) {
                throw new \RuntimeException("Manifest inválido: tamaño de {$filename} fuera de rango.");
            }
            $seenIndexes[(int) $part['index']] = true;
        }

        ksort($seenIndexes);
        if (array_keys($seenIndexes) !== range(1, $totalParts)) {
            throw new \RuntimeException('Manifest inválido: los índices de las partes deben ser 1..N consecutivos sin huecos ni duplicados.');
        }
    }

    /**
     * Durante el ensamblado coexisten todas las partes + el archivo final:
     * se necesita ~2x total_size_bytes libre, con margen. Rechaza el
     * manifest ANTES de aceptar ninguna subida si no hay espacio.
     */
    private function checkDiskSpace(int $totalSizeBytes): void
    {
        $dir = Storage::disk('local')->path(self::UPLOADS_DIR);
        $free = @disk_free_space($dir);
        $required = (int) ($totalSizeBytes * 2.1);

        if ($free !== false && $free < $required) {
            $freeMb = round($free / 1024 / 1024, 1);
            $requiredMb = round($required / 1024 / 1024, 1);
            throw new \RuntimeException("Espacio en disco insuficiente para recibir este backup. Disponibles: {$freeMb} MB, requeridos: ~{$requiredMb} MB.");
        }
    }

    public function status(RucBackupUpload $upload): array
    {
        $uploadedIndexes = $upload->parts()
            ->whereIn('status', [RucBackupUploadPart::STATUS_VERIFIED])
            ->orderBy('part_index')
            ->pluck('part_index')
            ->all();

        return [
            'uuid' => $upload->uuid,
            'status' => $upload->status,
            'uploaded_parts' => $uploadedIndexes,
            'received_parts' => $upload->received_parts,
            'received_bytes' => $upload->received_bytes,
            'total_parts' => $upload->total_parts,
            'total_size_bytes' => $upload->total_size_bytes,
            'ruc_backup_id' => $upload->ruc_backup_id,
            'error_message' => $upload->error_message,
        ];
    }

    /**
     * Recibe UNA parte. Nunca confía en el nombre de archivo del cliente
     * como ruta: la parte se guarda con un nombre controlado por el
     * servidor (part{index}.bin dentro del directorio de la sesión).
     */
    public function receivePart(RucBackupUpload $upload, int $index, UploadedFile $file, User $user): RucBackupUploadPart
    {
        if ((int) $upload->user_id !== (int) $user->id) {
            throw new \RuntimeException('Esta sesión de subida no te pertenece.');
        }

        if ($upload->isTerminal()) {
            throw new \RuntimeException('Esta sesión de subida ya no acepta partes (estado: '.$upload->status.').');
        }

        if ($upload->isExpired()) {
            $this->markFailed($upload, 'La sesión de subida expiró.');
            throw new \RuntimeException('La sesión de subida expiró. Inicia una nueva importación.');
        }

        /** @var RucBackupUploadPart|null $part */
        $part = $upload->parts()->where('part_index', $index)->first();
        if ($part === null) {
            throw new \RuntimeException("Índice de parte inválido: {$index}.");
        }

        if ($part->isVerified()) {
            // Ya se subió y verificó antes (reintento/resume) — idempotente.
            return $part;
        }

        if ($file->getClientOriginalName() !== $part->filename) {
            throw new \RuntimeException("Nombre de archivo inesperado para la parte {$index}: se esperaba \"{$part->filename}\".");
        }

        $actualSize = $file->getSize();
        if ($actualSize !== $part->size_bytes) {
            throw new \RuntimeException("Tamaño incorrecto en {$part->filename}: esperado {$part->size_bytes} bytes, recibido {$actualSize} bytes.");
        }

        $actualChecksum = hash_file('sha256', $file->getRealPath());
        if ($actualChecksum !== $part->checksum_sha256) {
            $part->update(['status' => RucBackupUploadPart::STATUS_FAILED]);
            throw new \RuntimeException("Checksum incorrecto en {$part->filename}.");
        }

        // Nombre en disco 100% controlado por el servidor — nunca el
        // filename declarado por el cliente/manifest.
        $storedName = sprintf('part%04d.bin', $index);
        $relativePath = $upload->temporary_directory.'/'.$storedName;
        $absolutePath = Storage::disk('local')->path($relativePath);

        if (! @rename($file->getRealPath(), $absolutePath) && ! @copy($file->getRealPath(), $absolutePath)) {
            throw new \RuntimeException("No se pudo guardar la parte {$index} en el servidor.");
        }

        $part->update([
            'storage_path' => $relativePath,
            'status' => RucBackupUploadPart::STATUS_VERIFIED,
        ]);

        $upload->update([
            'status' => RucBackupUpload::STATUS_UPLOADING,
            'received_parts' => $upload->parts()->where('status', RucBackupUploadPart::STATUS_VERIFIED)->count(),
            'received_bytes' => (int) $upload->parts()->where('status', RucBackupUploadPart::STATUS_VERIFIED)->sum('size_bytes'),
        ]);

        if ($upload->received_parts === $upload->total_parts) {
            return $this->assemble($upload->fresh())->parts()->where('part_index', $index)->first();
        }

        return $part->fresh();
    }

    /**
     * Ensambla el .dump final por streaming (nunca file_get_contents),
     * valida checksum total + contenido, registra el RucBackup, y borra
     * las partes temporales. Se dispara automáticamente al recibir la
     * última parte pendiente.
     */
    private function assemble(RucBackupUpload $upload): RucBackupUpload
    {
        $upload->update(['status' => RucBackupUpload::STATUS_ASSEMBLING]);

        $manifest = $upload->manifest_json;
        $originalFilename = $upload->original_filename; // p. ej. ruc_backup_2026-08-08-131307.dump
        $finalRelativePath = 'backups/ruc/'.$this->uniqueName($originalFilename);
        $finalAbsolutePath = Storage::disk('local')->path($finalRelativePath);

        try {
            $parts = $upload->parts()->orderBy('part_index')->get();

            $dest = fopen($finalAbsolutePath, 'wb');
            if ($dest === false) {
                throw new \RuntimeException('No se pudo crear el archivo final del backup.');
            }

            try {
                foreach ($parts as $part) {
                    $partAbsolutePath = Storage::disk('local')->path($part->storage_path);
                    $source = fopen($partAbsolutePath, 'rb');
                    if ($source === false) {
                        throw new \RuntimeException("No se pudo leer la parte {$part->part_index}.");
                    }
                    try {
                        if (stream_copy_to_stream($source, $dest) === false) {
                            throw new \RuntimeException("Error ensamblando la parte {$part->part_index}.");
                        }
                    } finally {
                        fclose($source);
                    }
                }
            } finally {
                fclose($dest);
            }

            $upload->update(['status' => RucBackupUpload::STATUS_VALIDATING]);

            $actualSize = filesize($finalAbsolutePath);
            if ($actualSize !== (int) $manifest['total_size_bytes']) {
                throw new \RuntimeException("Tamaño del backup ensamblado incorrecto: esperado {$manifest['total_size_bytes']} bytes, obtenido {$actualSize} bytes.");
            }

            $actualSha = hash_file('sha256', $finalAbsolutePath);
            if ($actualSha !== $manifest['sha256']) {
                throw new \RuntimeException('El SHA-256 del backup ensamblado no coincide con el del manifest. El archivo puede estar corrupto.');
            }

            // Formato + contenido: rechaza cualquier objeto ajeno a
            // ruc_records, exactamente igual que un import de un solo archivo.
            $this->backupService->assertDumpBelongsToRucRecords($finalAbsolutePath);

            // Copia del manifest junto al backup, para auditoría.
            // Basado en el nombre REAL ya usado para el dump (basename de
            // $finalRelativePath), nunca recalculado: uniqueName() no es
            // idempotente una vez que el archivo ya existe en disco (la
            // segunda llamada vería el archivo recién escrito y añadiría
            // OTRO sufijo aleatorio distinto, desincronizando el nombre del
            // manifest del nombre real del backup).
            $manifestRelativePath = 'backups/ruc/'.pathinfo(basename($finalRelativePath), PATHINFO_FILENAME).'.manifest.json';
            Storage::disk('local')->put($manifestRelativePath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            $recordCount = (int) ($manifest['total_records'] ?? 0);

            $backup = RucBackup::create([
                'name' => basename($finalRelativePath),
                'backup_type' => RucBackup::TYPE_UPLOADED,
                'storage_path' => $finalRelativePath,
                'file_size_bytes' => $actualSize,
                'checksum_sha256' => $actualSha,
                'total_records' => $recordCount,
                'status' => RucBackup::STATUS_COMPLETED,
                'created_by' => $upload->user_id,
            ]);

            $upload->update([
                'status' => RucBackupUpload::STATUS_COMPLETED,
                'ruc_backup_id' => $backup->id,
                'completed_at' => now(),
            ]);

            Log::info('RUC multipart backup assembled', [
                'upload_id' => $upload->id,
                'backup_id' => $backup->id,
                'size' => $actualSize,
                'parts' => $parts->count(),
                'user_id' => $upload->user_id,
            ]);
        } catch (\Throwable $e) {
            // Solo el bloque de arriba (ensamblar/validar/registrar) debe
            // poder marcar la sesión como fallida y borrar el archivo
            // final: si algo ahí falla, el backup nunca llegó a existir de
            // forma confiable. La limpieza de partes temporales (abajo) es
            // deliberadamente una operación APARTE — un backup ya creado y
            // registrado con éxito NUNCA debe borrarse ni reportarse como
            // fallido solo porque no se pudo borrar un archivo temporal
            // sobrante (visto en la práctica: fallos de borrado transitorios
            // en Windows/Docker Desktop sobre archivos recién cerrados).
            @unlink($finalAbsolutePath);
            $this->markFailed($upload, $e->getMessage());

            throw $e;
        }

        try {
            $this->cleanupTemporaryDirectory($upload);
        } catch (\Throwable $e) {
            Log::warning('RUC multipart temporary directory cleanup failed (backup already completed successfully)', [
                'upload_id' => $upload->id,
                'backup_id' => $upload->ruc_backup_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $upload->fresh();
    }

    private function uniqueName(string $originalFilename): string
    {
        // El original_filename ya viene con timestamp+extensión únicos del
        // lado de RUC Tools; se mantiene tal cual salvo colisión real.
        $relative = 'backups/ruc/'.$originalFilename;
        if (! Storage::disk('local')->exists($relative)) {
            return $originalFilename;
        }

        $info = pathinfo($originalFilename);
        $suffix = bin2hex(random_bytes(4));

        return $info['filename'].'_'.$suffix.'.'.($info['extension'] ?? 'dump');
    }

    private function markFailed(RucBackupUpload $upload, string $message): void
    {
        $upload->update([
            'status' => RucBackupUpload::STATUS_FAILED,
            'error_message' => substr($message, 0, 1000),
        ]);

        Log::error('RUC multipart backup failed', [
            'upload_id' => $upload->id,
            'error' => $message,
            'user_id' => $upload->user_id,
        ]);
    }

    public function cancel(RucBackupUpload $upload, User $user): void
    {
        if ((int) $upload->user_id !== (int) $user->id) {
            throw new \RuntimeException('Esta sesión de subida no te pertenece.');
        }

        if ($upload->isTerminal()) {
            return;
        }

        $upload->update(['status' => RucBackupUpload::STATUS_CANCELLED]);
        $this->cleanupTemporaryDirectory($upload);

        Log::info('RUC multipart backup cancelled', ['upload_id' => $upload->id, 'user_id' => $user->id]);
    }

    /**
     * Reintenta un par de veces con una espera corta: en la práctica, un
     * archivo recién cerrado (fclose en receivePart/assemble) puede seguir
     * bloqueado un instante más por el propio filesystem del host en
     * Windows/Docker Desktop, y un primer intento de borrado falla aunque
     * el archivo ya no esté realmente en uso.
     */
    public function cleanupTemporaryDirectory(RucBackupUpload $upload): void
    {
        if (! Storage::disk('local')->exists($upload->temporary_directory)) {
            return;
        }

        $attempts = 0;
        $lastError = null;
        while ($attempts < 3) {
            $attempts++;
            try {
                Storage::disk('local')->deleteDirectory($upload->temporary_directory);

                return;
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempts < 3) {
                    usleep(200000); // 200ms
                }
            }
        }

        throw $lastError;
    }

    /**
     * Uploads pendientes/en curso cuya sesión ya expiró: cancela y borra
     * sus partes temporales. Nunca toca uploads completados ni sus backups.
     */
    public function cleanupExpired(): int
    {
        $expired = RucBackupUpload::query()
            ->whereNotIn('status', [RucBackupUpload::STATUS_COMPLETED, RucBackupUpload::STATUS_CANCELLED, RucBackupUpload::STATUS_FAILED])
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $upload) {
            $upload->update(['status' => RucBackupUpload::STATUS_CANCELLED, 'error_message' => 'Sesión expirada (limpieza automática).']);
            $this->safeCleanup($upload);
        }

        $this->sweepOrphanedTemporaryDirectories();

        return $expired->count();
    }

    private function safeCleanup(RucBackupUpload $upload): void
    {
        try {
            $this->cleanupTemporaryDirectory($upload);
        } catch (\Throwable $e) {
            Log::warning('RUC multipart cleanup: could not delete temporary directory', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Red de seguridad: partes temporales que sobrevivieron a una sesión ya
     * terminal (completed/cancelled/failed) porque el borrado en assemble()
     * agotó sus reintentos — nunca debería pasar seguido, pero si pasa no
     * debe quedar ocupando espacio para siempre. Solo actúa sobre sesiones
     * con al menos 1 hora desde su última actualización, para no chocar con
     * los reintentos que ya corren justo al terminar assemble().
     */
    private function sweepOrphanedTemporaryDirectories(): void
    {
        $stale = RucBackupUpload::query()
            ->whereIn('status', [RucBackupUpload::STATUS_COMPLETED, RucBackupUpload::STATUS_CANCELLED, RucBackupUpload::STATUS_FAILED])
            ->where('updated_at', '<', now()->subHour())
            ->get();

        foreach ($stale as $upload) {
            if (Storage::disk('local')->exists($upload->temporary_directory)) {
                $this->safeCleanup($upload);
            }
        }
    }
}
