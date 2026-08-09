<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Services;

use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Support\RucBackupArchive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Genera un backup .rucbackup: un único archivo con el padrón troceado en
 * chunks CSV comprimidos con zstd (ver RucBackupArchive para el formato).
 *
 * MEMORIA CONSTANTE. Ni una sola fila de ruc_records pasa por PHP: cada lote
 * se genera con
 *
 *     psql "\copy (SELECT ... WHERE id > X ORDER BY id LIMIT N) TO STDOUT CSV"
 *       | zstd -T0 -<nivel> -o chunk.csv.zst
 *
 * PHP solo lanza el proceso y mueve el fichero resultante al ZIP. Da igual
 * que la tabla tenga 18M, 30M o 50M filas: el pico de RAM del proceso PHP es
 * el mismo.
 *
 * PAGINACIÓN POR CLAVE, NO OFFSET. Se avanza con `WHERE id > $lastId ORDER BY
 * id LIMIT $batch`, nunca con OFFSET: OFFSET obliga a PostgreSQL a recorrer y
 * descartar las filas anteriores, así que el coste del chunk N crece con N y
 * el backup se degrada cuadráticamente. Con keyset cada chunk cuesta lo mismo
 * (recorrido del índice primario).
 */
class RucChunkedBackupService
{
    private const BACKUP_DIR = 'backups/ruc';

    private const TABLE = 'ruc_records';

    public function __construct(private readonly RucBackupProcessRunner $runner) {}

    /**
     * @param  null|callable(array<string, mixed>): void  $onProgress
     */
    public function create(?User $user = null, ?int $batchSize = null, ?callable $onProgress = null): RucBackup
    {
        $batchSize = $this->resolveBatchSize($batchSize);
        $name = $this->generateFileName();
        $relativePath = self::BACKUP_DIR.'/'.$name;

        Storage::disk('local')->makeDirectory(self::BACKUP_DIR);
        $absolutePath = Storage::disk('local')->path($relativePath);

        $backup = RucBackup::create([
            'name' => $name,
            'backup_type' => RucBackup::TYPE_MANUAL,
            'storage_path' => $relativePath,
            'status' => RucBackup::STATUS_CREATING,
            'created_by' => $user?->id,
        ]);

        $workDir = $this->makeWorkDir();

        try {
            $result = $this->buildArchive($absolutePath, $workDir, $batchSize, $onProgress);

            $backup->update([
                'file_size_bytes' => filesize($absolutePath),
                'checksum_sha256' => hash_file('sha256', $absolutePath),
                'total_records' => $result['total_records'],
                'status' => RucBackup::STATUS_COMPLETED,
            ]);

            Log::info('RUC chunked backup created', [
                'backup_id' => $backup->id,
                'records' => $result['total_records'],
                'chunks' => $result['total_batches'],
                'batch_size' => $batchSize,
                'size' => filesize($absolutePath),
                'user_id' => $user?->id,
            ]);

            return $backup->fresh();
        } catch (\Throwable $e) {
            Log::error('RUC chunked backup failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            $backup->update([
                'status' => RucBackup::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            @unlink($absolutePath);

            throw $e;
        } finally {
            $this->removeWorkDir($workDir);
        }
    }

    /**
     * @param  null|callable(array<string, mixed>): void  $onProgress
     * @return array{total_records: int, total_batches: int}
     */
    private function buildArchive(string $archivePath, string $workDir, int $batchSize, ?callable $onProgress): array
    {
        $totalRecords = (int) DB::table(self::TABLE)->count();
        $totalBatches = $totalRecords === 0 ? 0 : (int) ceil($totalRecords / $batchSize);

        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear el archivo de backup en {$archivePath}.");
        }

        $chunks = [];
        $lastId = 0;
        $number = 0;
        $recordsWritten = 0;
        $startedAt = microtime(true);
        $chunkFiles = [];

        try {
            while (true) {
                $number++;
                $chunkPath = $workDir.'/chunk_'.$number.'.csv.zst';

                $written = $this->dumpChunk($lastId, $batchSize, $chunkPath);

                if ($written['records'] === 0) {
                    @unlink($chunkPath);
                    $number--;
                    break;
                }

                $entryName = RucBackupArchive::chunkEntryName($number);

                $chunks[] = [
                    'number' => $number,
                    'filename' => $entryName,
                    'records' => $written['records'],
                    'first_id' => $written['first_id'],
                    'last_id' => $written['last_id'],
                    'uncompressed_size' => $written['uncompressed_size'],
                    'compressed_size' => filesize($chunkPath),
                    'sha256' => hash_file('sha256', $chunkPath),
                ];

                // addFile no lee el fichero aquí: libzip lo streamea al
                // cerrar el ZIP. Por eso los temporales deben sobrevivir
                // hasta después de $zip->close().
                $zip->addFile($chunkPath, $entryName);
                $zip->setCompressionName($entryName, ZipArchive::CM_STORE);
                $chunkFiles[] = $chunkPath;

                $lastId = $written['last_id'];
                $recordsWritten += $written['records'];

                if ($onProgress !== null) {
                    $elapsed = max(0.001, microtime(true) - $startedAt);
                    $onProgress([
                        'batch' => $number,
                        'total_batches' => $totalBatches,
                        'records' => $recordsWritten,
                        'total_records' => $totalRecords,
                        'records_per_second' => (int) round($recordsWritten / $elapsed),
                        'elapsed' => $elapsed,
                    ]);
                }

                if ($written['records'] < $batchSize) {
                    break; // último lote, incompleto por definición
                }
            }

            $manifest = [
                'format' => RucBackupArchive::FORMAT,
                'format_version' => RucBackupArchive::FORMAT_VERSION,
                'created_at' => now()->toIso8601String(),
                'application_version' => (string) config('version.current'),
                'schema_version' => $this->schemaVersion(),
                'source_table' => self::TABLE,
                'total_records' => $recordsWritten,
                'batch_size' => $batchSize,
                'total_batches' => count($chunks),
                'columns' => RucBackupArchive::COLUMNS,
                'compression' => RucBackupArchive::COMPRESSION,
                'compression_level' => (int) config('ruc.backup.chunked.zstd_level', 3),
                'chunks' => $chunks,
                // Checksum global del conjunto de chunks: detecta reordenación
                // o sustitución de un chunk por otro válido pero de otro backup.
                'chunks_sha256' => hash('sha256', implode('', array_column($chunks, 'sha256'))),
            ];

            $zip->addFromString(
                RucBackupArchive::MANIFEST_ENTRY,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            if (! $zip->close()) {
                throw new RuntimeException('No se pudo cerrar el archivo .rucbackup; puede haber quedado incompleto.');
            }
        } catch (\Throwable $e) {
            @$zip->close();
            throw $e;
        } finally {
            foreach ($chunkFiles as $file) {
                @unlink($file);
            }
        }

        return ['total_records' => $recordsWritten, 'total_batches' => count($chunks)];
    }

    /**
     * Vuelca un lote a disco ya comprimido, sin pasar por PHP.
     *
     * @return array{records: int, first_id: int, last_id: int, uncompressed_size: int}
     */
    private function dumpChunk(int $afterId, int $limit, string $outputPath): array
    {
        // Se consultan los límites del lote ANTES de volcarlo: son dos
        // lecturas de índice, baratas, y permiten registrar first_id/last_id
        // en el manifest (necesarios para verificar la reanudación).
        $bounds = DB::selectOne(
            'SELECT MIN(id) AS first_id, MAX(id) AS last_id, COUNT(*) AS records
             FROM (SELECT id FROM '.self::TABLE.' WHERE id > ? ORDER BY id LIMIT ?) AS batch',
            [$afterId, $limit]
        );

        $records = (int) ($bounds->records ?? 0);

        if ($records === 0) {
            return ['records' => 0, 'first_id' => 0, 'last_id' => $afterId, 'uncompressed_size' => 0];
        }

        $columns = implode(', ', RucBackupArchive::COLUMNS);
        $copy = sprintf(
            '\copy (SELECT %s FROM %s WHERE id > %d ORDER BY id LIMIT %d) TO STDOUT WITH (FORMAT csv)',
            $columns,
            self::TABLE,
            $afterId,
            $limit
        );

        $level = (int) config('ruc.backup.chunked.zstd_level', 3);
        $threads = (int) config('ruc.backup.chunked.zstd_threads', 0);

        // PIPESTATUS: sin esto, un fallo de psql quedaría enmascarado por el
        // éxito de zstd (que comprimiría felizmente una entrada vacía) y el
        // backup saldría truncado sin avisar.
        $shell = sprintf(
            'set -o pipefail; psql %s --no-psqlrc --set=ON_ERROR_STOP=1 -c %s | zstd -q -T%d -%d -o %s',
            $this->psqlConnectionArgs(),
            escapeshellarg($copy),
            $threads,
            $level,
            escapeshellarg($outputPath)
        );

        $process = Process::fromShellCommandline($shell);
        $process->setTimeout(null);
        $process->setEnv(['PGPASSWORD' => (string) $this->dbConfig('password')]);
        $this->runner->run($process);

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Falló la generación del lote: '.trim($process->getErrorOutput()));
        }

        if (! is_file($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('El lote se generó vacío; el backup se cancela para no producir un archivo incompleto.');
        }

        return [
            'records' => $records,
            'first_id' => (int) $bounds->first_id,
            'last_id' => (int) $bounds->last_id,
            'uncompressed_size' => $this->uncompressedSize($outputPath),
        ];
    }

    /**
     * Tamaño real del CSV antes de comprimir.
     *
     * `zstd -l` NO sirve aquí: solo escribe el tamaño original en la
     * cabecera del frame cuando comprime desde un fichero, y aquí el CSV
     * llega por tubería desde psql, así que zstd lo desconoce. Se obtiene
     * con una pasada de descompresión a `wc -c`, que no materializa nada:
     * los bytes se cuentan al vuelo y se descartan. zstd descomprime del
     * orden de 1 GB/s, así que el coste es de ~1 s por lote de 500k filas.
     *
     * El valor alimenta el manifest y la estimación de espacio libre previa
     * al restore; si la pasada fallara, un 0 solo degrada esa estimación a
     * la heurística de reserva, nunca invalida el backup.
     */
    private function uncompressedSize(string $path): int
    {
        $process = Process::fromShellCommandline(
            'set -o pipefail; zstd -d -q -c '.escapeshellarg($path).' | wc -c'
        );
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            return 0;
        }

        return (int) trim($process->getOutput());
    }

    public function resolveBatchSize(?int $requested): int
    {
        $size = $requested ?? (int) config('ruc.backup.chunked.batch_size', 500000);

        if ($size < 1000) {
            throw new RuntimeException('El tamaño de lote mínimo es 1000 registros.');
        }

        return $size;
    }

    private function schemaVersion(): string
    {
        // Última migración aplicada: identifica el esquema con el que se creó
        // el backup sin inventar un número de versión paralelo.
        $latest = DB::table('migrations')->orderByDesc('id')->value('migration');

        return (string) ($latest ?? 'unknown');
    }

    private function generateFileName(): string
    {
        return sprintf(
            'ruc_backup_%s_%s.%s',
            now()->format('Y-m-d_His'),
            bin2hex(random_bytes(3)),
            RucBackupArchive::EXTENSION
        );
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().'/ruc_backup_'.bin2hex(random_bytes(6));

        if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio de trabajo {$dir}.");
        }

        return $dir;
    }

    private function removeWorkDir(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    private function psqlConnectionArgs(): string
    {
        return sprintf(
            '--host=%s --port=%s --username=%s --dbname=%s',
            escapeshellarg((string) $this->dbConfig('host')),
            escapeshellarg((string) $this->dbConfig('port', '5432')),
            escapeshellarg((string) $this->dbConfig('username')),
            escapeshellarg((string) $this->dbConfig('database'))
        );
    }

    private function dbConfig(string $key, ?string $default = null): ?string
    {
        return config("database.connections.pgsql.{$key}", $default);
    }
}
