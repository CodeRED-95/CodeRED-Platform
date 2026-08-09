<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Ruc\Services\RucBackupService;
use App\Modules\Ruc\Services\RucChunkedBackupService;
use App\Modules\Ruc\Support\RucBackupArchive;
use Illuminate\Console\Command;

class RucBackupCommand extends Command
{
    protected $signature = 'ruc:backup
        {--user= : ID del usuario que realiza el backup}
        {--batch-size= : Registros por lote interno (por defecto ruc.backup.chunked.batch_size)}
        {--legacy : Genera un .dump de pg_dump en lugar del formato .rucbackup}';

    protected $description = 'Crear un backup (solo datos) de ruc_records en formato .rucbackup troceado';

    public function handle(): int
    {
        $user = $this->option('user') ? User::find($this->option('user')) : null;

        if ($this->option('legacy')) {
            return $this->createLegacyBackup($user);
        }

        $service = app(RucChunkedBackupService::class);

        try {
            $batchSize = $service->resolveBatchSize(
                $this->option('batch-size') !== null ? (int) $this->option('batch-size') : null
            );
        } catch (\Throwable $e) {
            $this->error('❌ '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Creando backup .rucbackup…');
        $this->line('  Lote: '.number_format($batchSize).' registros');
        $this->line('  Compresión: zstd nivel '.config('ruc.backup.chunked.zstd_level'));
        $this->newLine();

        $bar = null;
        $startedAt = microtime(true);

        try {
            $backup = $service->create($user, $batchSize, function (array $p) use (&$bar): void {
                if ($bar === null) {
                    $bar = $this->output->createProgressBar(max(1, $p['total_batches']));
                    $bar->setFormat(' %current%/%max% lotes [%bar%] %message%');
                    $bar->start();
                }
                $bar->setMessage(sprintf(
                    '%s/%s registros · %s reg/s',
                    number_format($p['records']),
                    number_format($p['total_records']),
                    number_format($p['records_per_second'])
                ));
                $bar->setProgress($p['batch']);
            });

            $bar?->finish();
            $this->newLine(2);

            $manifest = RucBackupArchive::readManifest($backup->absolutePath());
            $elapsed = microtime(true) - $startedAt;
            $uncompressed = array_sum(array_column($manifest['chunks'], 'uncompressed_size'));
            $ratio = $uncompressed > 0 ? $uncompressed / max(1, (int) $backup->file_size_bytes) : 0;

            $this->info('✅ Backup creado');
            $this->table(['Propiedad', 'Valor'], [
                ['ID', $backup->id],
                ['Archivo', $backup->name],
                ['Registros', number_format((int) $backup->total_records)],
                ['Lotes', $manifest['total_batches']],
                ['Tamaño', $backup->formattedSize()],
                ['Ratio compresión', $ratio > 0 ? round($ratio, 2).'x' : 'N/D'],
                ['Duración', round($elapsed, 1).' s'],
                ['Velocidad', number_format((int) round(((int) $backup->total_records) / max(0.001, $elapsed))).' reg/s'],
                ['RAM máx. PHP', $this->formatBytes(memory_get_peak_usage(true))],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $bar?->finish();
            $this->newLine(2);
            $this->error('❌ Backup fallido: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Compatibilidad: sigue disponible para generar el formato antiguo si
     * alguien lo necesita puntualmente, pero no es el camino recomendado.
     */
    private function createLegacyBackup(?User $user): int
    {
        $this->warn('Formato legacy .dump: no es reanudable ni troceado. Preferir .rucbackup.');

        try {
            $backup = app(RucBackupService::class)->create($user);
            $this->info("✅ Backup legacy creado: {$backup->name} ({$backup->formattedSize()})");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Backup fallido: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $i = 0;
        for (; $value > 1024 && $i < count($units) - 1; $i++) {
            $value /= 1024;
        }

        return round($value, 2).' '.$units[$i];
    }
}
