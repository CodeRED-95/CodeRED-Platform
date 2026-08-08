<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Services;

use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Backup y restore de la tabla ruc_records. Únicamente datos: el schema de
 * ruc_records sigue siendo responsabilidad exclusiva de las migrations de
 * Laravel, nunca del dump (--data-only en todo momento). Ver
 * docs-ruc/BACKUP_SYSTEM.md para el diseño completo.
 */
class RucBackupService
{
    private const BACKUP_DIR = 'backups/ruc'; // relativo al disco "local"

    /** Tabla que todo backup RUC debe contener, y la única que puede contener. */
    private const EXPECTED_TABLE = 'ruc_records';

    /**
     * Objetos de PostgreSQL que puede legítimamente traer un dump de
     * --table=ruc_records: la tabla, su secuencia de id, y cualquier índice
     * o constraint propio (ruc_records_pkey, ruc_records_condicion_index,
     * etc. — PostgreSQL siempre los nombra con el prefijo de la tabla).
     * Cualquier otro nombre de tabla/secuencia en el TOC hace que se
     * rechace el archivo: así un backup subido por un usuario nunca puede
     * tocar otra tabla. Verificado contra `pg_tables`: ninguna otra tabla
     * del esquema (ruc_backups, ruc_imports, ruc_staging, ...) comparte el
     * prefijo "ruc_records", por lo que el match por prefijo es seguro.
     */
    private const ALLOWED_TOC_PATTERN = '/^ruc_records(_.*)?$/';

    public function __construct()
    {
        if (! Storage::disk('local')->exists(self::BACKUP_DIR)) {
            Storage::disk('local')->makeDirectory(self::BACKUP_DIR);
        }
    }

    /**
     * Crea un backup (solo datos) de ruc_records.
     */
    public function create(?User $user = null): RucBackup
    {
        $name = $this->generateFileName('ruc_backup');

        return $this->dumpToNewBackup($name, RucBackup::TYPE_MANUAL, $user);
    }

    /**
     * Backup de seguridad tomado automáticamente antes de un restore.
     */
    public function createSafetyBackup(?User $user = null): RucBackup
    {
        $name = $this->generateFileName('ruc_safety_before_restore');

        return $this->dumpToNewBackup($name, RucBackup::TYPE_SAFETY, $user);
    }

    private function generateFileName(string $prefix): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $random = bin2hex(random_bytes(4));

        return "{$prefix}_{$timestamp}_{$random}.".RucBackup::FILE_EXTENSION;
    }

    /**
     * Ejecuta pg_dump y registra el resultado en ruc_backups.
     *
     * Pasos (según BACKUP_SYSTEM.md):
     *   1. pg_dump --data-only --table=ruc_records
     *   2. comprobar exit code / archivo existe / size > 0
     *   3. pg_restore --list y confirmar que solo contiene ruc_records
     *   4. SHA-256
     *   5. contar registros actuales
     *   6. marcar como completed (o failed si algo de esto falla)
     */
    private function dumpToNewBackup(string $name, string $type, ?User $user): RucBackup
    {
        $relativePath = self::BACKUP_DIR.'/'.$name;
        $absolutePath = Storage::disk('local')->path($relativePath);

        $backup = RucBackup::create([
            'name' => $name,
            'backup_type' => $type,
            'storage_path' => $relativePath,
            'status' => RucBackup::STATUS_CREATING,
            'created_by' => $user?->id,
        ]);

        try {
            $this->runPgDump($absolutePath);

            if (! file_exists($absolutePath)) {
                throw new \RuntimeException('El archivo de backup no se creó.');
            }

            $fileSize = filesize($absolutePath);
            if ($fileSize === 0) {
                throw new \RuntimeException('El archivo de backup quedó vacío.');
            }

            // pg_restore --list debe confirmar formato Y contenido antes de
            // aceptar el archivo como backup válido.
            $this->assertDumpBelongsToRucRecords($absolutePath);

            $checksum = hash_file('sha256', $absolutePath);
            $recordCount = DB::table('ruc_records')->count();

            $backup->update([
                'file_size_bytes' => $fileSize,
                'checksum_sha256' => $checksum,
                'total_records' => $recordCount,
                'status' => RucBackup::STATUS_COMPLETED,
            ]);

            Log::info('RUC backup created', [
                'backup_id' => $backup->id,
                'type' => $type,
                'records' => $recordCount,
                'size' => $fileSize,
                'user_id' => $user?->id,
            ]);

            return $backup->fresh();
        } catch (\Throwable $e) {
            Log::error('RUC backup failed', [
                'backup_id' => $backup->id,
                'type' => $type,
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);

            $backup->update([
                'status' => RucBackup::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            if (file_exists($absolutePath)) {
                @unlink($absolutePath);
            }

            throw $e;
        }
    }

    private function runPgDump(string $outputPath): void
    {
        $backupDir = dirname($outputPath);
        if (! is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $command = [
            'pg_dump',
            '--host='.$this->dbConfig('host'),
            '--port='.$this->dbConfig('port', '5432'),
            '--username='.$this->dbConfig('username'),
            '--table='.self::EXPECTED_TABLE,
            '--format=custom',
            '--data-only',      // el schema lo controlan las migrations de Laravel, nunca el dump
            '--no-owner',
            '--no-privileges',
            '--file='.$outputPath,
            $this->dbConfig('database'),
        ];

        $process = new Process($command);
        $process->setTimeout(null); // ruc_records puede tener millones de filas; sin límite artificial
        $process->setEnv(['PGPASSWORD' => (string) $this->dbConfig('password')]);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            Log::error('pg_dump failed', [
                'stderr' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode(),
            ]);
            throw new \RuntimeException('pg_dump falló: '.$process->getErrorOutput());
        }
    }

    /**
     * Importa un backup subido manualmente (archivo ya guardado en disco).
     * Importar NO restaura — solo valida y registra el archivo.
     */
    public function import(string $absolutePath, string $originalName, ?User $user = null): RucBackup
    {
        if (! file_exists($absolutePath) || filesize($absolutePath) === 0) {
            throw new \RuntimeException('El archivo está vacío o no existe.');
        }

        // Rechaza cualquier archivo que no sea un dump de ruc_records ANTES
        // de aceptarlo como backup válido.
        $this->assertDumpBelongsToRucRecords($absolutePath);

        $name = $this->generateFileName('ruc_backup_imported');
        $relativePath = self::BACKUP_DIR.'/'.$name;
        $finalAbsolutePath = Storage::disk('local')->path($relativePath);

        if (! @rename($absolutePath, $finalAbsolutePath)) {
            // rename() puede fallar entre filesystems distintos (p. ej. /tmp
            // en otro punto de montaje); copiar+borrar es el fallback seguro.
            if (! @copy($absolutePath, $finalAbsolutePath)) {
                throw new \RuntimeException('No se pudo guardar el archivo importado.');
            }
            @unlink($absolutePath);
        }

        $fileSize = filesize($finalAbsolutePath);
        $checksum = hash_file('sha256', $finalAbsolutePath);
        $recordCount = $this->countRowsInDump($finalAbsolutePath);

        $backup = RucBackup::create([
            'name' => $name,
            'backup_type' => RucBackup::TYPE_MANUAL,
            'storage_path' => $relativePath,
            'file_size_bytes' => $fileSize,
            'checksum_sha256' => $checksum,
            'total_records' => $recordCount,
            'status' => RucBackup::STATUS_COMPLETED,
            'created_by' => $user?->id,
        ]);

        Log::info('RUC backup imported', [
            'backup_id' => $backup->id,
            'original_name' => $originalName,
            'size' => $fileSize,
            'user_id' => $user?->id,
        ]);

        return $backup;
    }

    /**
     * Restaura ruc_records desde un backup.
     *
     * Orden (BACKUP_SYSTEM.md):
     *   1. el archivo existe
     *   2. checksum SHA-256 coincide
     *   3. pg_restore --list confirma formato
     *   4. el dump contiene EXCLUSIVAMENTE objetos de ruc_records
     *   5. se crea un safety backup del estado actual
     *   6. si el safety backup falla: ABORTAR, nunca restaurar sin él
     *   7. restore atómico (TRUNCATE + datos en una sola transacción de
     *      psql — ver restoreDataAtomically())
     *   8. verificar resultado
     *   9. auditoría (log)
     */
    public function restore(RucBackup $backup, ?User $performedBy = null): array
    {
        if (! $backup->isCompleted()) {
            throw new \RuntimeException('El backup debe estar completado para restaurar.');
        }

        if (! $backup->fileExists()) {
            throw new \RuntimeException('El archivo de backup no existe en el servidor.');
        }

        $absolutePath = $backup->absolutePath();

        // 2. Checksum
        if ($backup->checksum_sha256) {
            $actualChecksum = hash_file('sha256', $absolutePath);
            if ($actualChecksum !== $backup->checksum_sha256) {
                throw new \RuntimeException('El checksum del archivo no coincide con el registrado. El backup puede estar corrupto.');
            }
        }

        // 3-4. Formato y contenido
        $this->assertDumpBelongsToRucRecords($absolutePath);

        Log::info('Starting RUC restore', ['backup_id' => $backup->id, 'performed_by' => $performedBy?->id]);
        $startedAt = microtime(true);

        // 5-6. Safety backup obligatorio
        try {
            $safetyBackup = $this->createSafetyBackup($performedBy);
        } catch (\Throwable $e) {
            Log::error('Restore aborted: safety backup failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('No se pudo crear el backup de seguridad; restore cancelado. '.$e->getMessage());
        }

        $recordsBefore = DB::table('ruc_records')->count();

        // 7. Restore atómico
        $this->restoreDataAtomically($absolutePath);

        // 8. Verificar resultado
        $recordsAfter = DB::table('ruc_records')->count();
        if ($backup->total_records !== null && $recordsAfter !== $backup->total_records) {
            Log::warning('Restored record count differs from backup metadata', [
                'backup_id' => $backup->id,
                'expected' => $backup->total_records,
                'actual' => $recordsAfter,
            ]);
        }

        $duration = (int) round(microtime(true) - $startedAt);

        // 9. Auditoría
        Log::info('RUC restore completed', [
            'backup_id' => $backup->id,
            'safety_backup_id' => $safetyBackup->id,
            'records_before' => $recordsBefore,
            'records_after' => $recordsAfter,
            'duration_seconds' => $duration,
            'performed_by' => $performedBy?->id,
        ]);

        return [
            'records_restored' => $recordsAfter,
            'safety_backup_id' => $safetyBackup->id,
            'duration_seconds' => $duration,
        ];
    }

    /**
     * TRUNCATE ruc_records + carga de datos del dump, TODO dentro de UNA
     * sola transacción real de PostgreSQL.
     *
     * pg_restore --single-transaction abre su PROPIA conexión y transacción
     * — no se puede anidar ahí un TRUNCATE emitido por Laravel/PDO desde
     * otra conexión (dejaría de ser atómico: si pg_restore fallara después,
     * el TRUNCATE ya habría quedado confirmado por su cuenta). La forma
     * correcta de que "vaciar la tabla" y "cargar los datos nuevos" sean
     * una única operación atómica es:
     *
     *   1. pg_restore --data-only -f archivo.sql backup.dump
     *      (convierte el dump a SQL plano SIN tocar ninguna base de datos)
     *   2. armar un script wrapper.sql: BEGIN; TRUNCATE ruc_records;
     *      \i archivo.sql; COMMIT;
     *   3. ejecutar ESE wrapper con una sola sesión de psql
     *      (-v ON_ERROR_STOP=1)
     *
     * Si el COPY del paso 3 falla, psql nunca llega al COMMIT; al cerrar la
     * conexión, PostgreSQL revierte automáticamente TODA la transacción
     * abierta — incluido el TRUNCATE — dejando ruc_records exactamente como
     * estaba. Verificado con pruebas manuales (dump truncado a mitad de
     * archivo): la tabla queda intacta cuando esto falla.
     */
    private function restoreDataAtomically(string $dumpPath): void
    {
        $workDir = sys_get_temp_dir();
        $dataSqlPath = $workDir.'/ruc_restore_data_'.bin2hex(random_bytes(6)).'.sql';
        $wrapperSqlPath = $workDir.'/ruc_restore_wrapper_'.bin2hex(random_bytes(6)).'.sql';

        try {
            // 1. Convertir el dump a SQL plano (no toca ninguna BD).
            $convert = new Process([
                'pg_restore',
                '--data-only',
                '--no-owner',
                '--no-privileges',
                '--file='.$dataSqlPath,
                $dumpPath,
            ]);
            $convert->setTimeout(null);
            $convert->run();
            if (! $convert->isSuccessful()) {
                throw new \RuntimeException('No se pudo preparar el archivo de restore: '.$convert->getErrorOutput());
            }

            // 2. Armar el wrapper transaccional.
            $wrapperSql = "BEGIN;\nTRUNCATE TABLE ".self::EXPECTED_TABLE.";\n\\i ".$dataSqlPath."\nCOMMIT;\n";
            file_put_contents($wrapperSqlPath, $wrapperSql);

            // 3. Ejecutar TODO en una sola sesión de psql.
            $restore = new Process([
                'psql',
                '--host='.$this->dbConfig('host'),
                '--port='.$this->dbConfig('port', '5432'),
                '--username='.$this->dbConfig('username'),
                '--dbname='.$this->dbConfig('database'),
                '--set=ON_ERROR_STOP=1',
                '--no-psqlrc',
                '--file='.$wrapperSqlPath,
            ]);
            $restore->setTimeout(null);
            $restore->setEnv(['PGPASSWORD' => (string) $this->dbConfig('password')]);
            $restore->run();

            if (! $restore->isSuccessful()) {
                // psql nunca llegó al COMMIT: Postgres ya revirtió todo por
                // su cuenta al cerrar la conexión. ruc_records quedó como
                // estaba antes de este intento.
                Log::error('Atomic restore failed; PostgreSQL rolled back automatically', [
                    'stderr' => $restore->getErrorOutput(),
                    'exit_code' => $restore->getExitCode(),
                ]);
                throw new \RuntimeException('La restauración falló y fue revertida automáticamente. ruc_records no se modificó. Detalle: '.$restore->getErrorOutput());
            }
        } finally {
            @unlink($dataSqlPath);
            @unlink($wrapperSqlPath);
        }
    }

    /**
     * Verifica que el archivo sea un dump custom de PostgreSQL válido Y que
     * el TOC (`pg_restore --list`) contenga EXCLUSIVAMENTE objetos de
     * ruc_records. Cualquier otra tabla/secuencia presente hace que se
     * rechace el archivo por completo — así un dump subido por un usuario
     * nunca puede usarse para tocar otra parte de la base de datos.
     */
    public function assertDumpBelongsToRucRecords(string $filePath): void
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            throw new \RuntimeException('El archivo no existe o está vacío.');
        }

        $list = new Process(['pg_restore', '--list', $filePath]);
        $list->setTimeout(120);
        $list->run();

        if (! $list->isSuccessful()) {
            throw new \RuntimeException('El archivo no es un dump válido de PostgreSQL (formato custom de pg_dump). '.$list->getErrorOutput());
        }

        $toc = $list->getOutput();
        $sawExpectedTable = false;

        foreach (explode("\n", $toc) as $line) {
            $line = trim($line);
            // Líneas de comentario del encabezado (";  Archive created at...").
            if ($line === '' || str_starts_with($line, ';')) {
                continue;
            }

            // Formato real: "3243; 1259 24592 TABLE public ruc_records codered"
            // El orden importa: alternativas más largas/específicas primero
            // (p. ej. "SEQUENCE OWNED BY" antes que "SEQUENCE"), o la
            // alternancia hace match parcial y desplaza qué token cae en
            // cada grupo capturado (bug real: "SEQUENCE OWNED BY ... ruc_records_id_seq"
            // matcheaba solo "SEQUENCE", capturando "OWNED"/"BY" como si
            // fueran schema/nombre).
            if (! preg_match('/^\d+;\s+\d+\s+\d+\s+(TABLE DATA|SEQUENCE OWNED BY|SEQUENCE SET|SEQUENCE|TABLE|CONSTRAINT|INDEX|DEFAULT)\s+(\S+)\s+(\S+)/', $line, $m)) {
                continue;
            }

            $objectName = $m[3];

            if (! preg_match(self::ALLOWED_TOC_PATTERN, $objectName)) {
                throw new \RuntimeException(
                    "El archivo contiene el objeto \"{$objectName}\", ajeno a ruc_records. ".
                    'Por seguridad solo se aceptan backups que contengan exclusivamente la tabla ruc_records.'
                );
            }

            if ($objectName === self::EXPECTED_TABLE) {
                $sawExpectedTable = true;
            }
        }

        if (! $sawExpectedTable) {
            throw new \RuntimeException('El archivo no contiene la tabla "ruc_records" esperada. Este backup no corresponde al padrón RUC.');
        }
    }

    /**
     * Cuenta cuántas filas trae un dump sin restaurarlo, contando las
     * líneas de datos entre "COPY ... FROM stdin;" y "\.". Usado solo para
     * mostrar metadata informativa al importar (no crítico si falla).
     *
     * Escribe a un archivo temporal y lo recorre línea por línea (fopen /
     * fgets) en vez de capturar toda la salida de Process: un dump de
     * millones de filas nunca debe pasar completo por la memoria de PHP.
     */
    private function countRowsInDump(string $dumpPath): ?int
    {
        $tmpSqlPath = sys_get_temp_dir().'/ruc_count_'.bin2hex(random_bytes(6)).'.sql';

        $convert = new Process(['pg_restore', '--data-only', '--no-owner', '--no-privileges', '--file='.$tmpSqlPath, $dumpPath]);
        $convert->setTimeout(120);
        $convert->run();

        if (! $convert->isSuccessful() || ! file_exists($tmpSqlPath)) {
            @unlink($tmpSqlPath);

            return null;
        }

        $handle = fopen($tmpSqlPath, 'rb');
        if ($handle === false) {
            @unlink($tmpSqlPath);

            return null;
        }

        $inCopyBlock = false;
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\n");
            if (str_starts_with($line, 'COPY ')) {
                $inCopyBlock = true;

                continue;
            }
            if ($inCopyBlock && $line === '\\.') {
                break;
            }
            if ($inCopyBlock) {
                $count++;
            }
        }
        fclose($handle);
        @unlink($tmpSqlPath);

        return $count;
    }

    private function dbConfig(string $key, ?string $default = null): ?string
    {
        return config("database.connections.pgsql.{$key}", $default);
    }
}
