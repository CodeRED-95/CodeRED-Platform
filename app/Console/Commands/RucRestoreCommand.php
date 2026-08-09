<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Services\RucBackupService;
use App\Modules\Ruc\Services\RucChunkedRestoreService;
use App\Modules\Ruc\Support\RucBackupArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RucRestoreCommand extends Command
{
    protected $signature = 'ruc:restore
        {backup : ID del backup o ruta a un archivo .rucbackup}
        {--resume : Reanuda la última restauración interrumpida desde su checkpoint}
        {--force : No pedir confirmación}';

    protected $description = 'Restaurar ruc_records desde un .rucbackup (por lotes, reanudable) o desde un .dump legacy';

    public function handle(): int
    {
        $backup = $this->resolveBackup((string) $this->argument('backup'));

        if ($backup === null) {
            return self::FAILURE;
        }

        if (! $backup->isCompleted()) {
            $this->error("❌ El backup debe estar completado. Estado: {$backup->status}");

            return self::FAILURE;
        }

        return $backup->isChunked()
            ? $this->restoreChunked($backup)
            : $this->restoreLegacy($backup);
    }

    private function resolveBackup(string $argument): ?RucBackup
    {
        // Ruta a un archivo: se registra (o se reutiliza si ya existe) para
        // que la operación quede auditada igual que un backup del sistema.
        if (is_file($argument)) {
            try {
                $this->info('Registrando el archivo indicado…');

                return app(RucBackupService::class)->import($argument, basename($argument));
            } catch (\Throwable $e) {
                $this->error('❌ No se pudo registrar el archivo: '.$e->getMessage());

                return null;
            }
        }

        $backup = RucBackup::find($argument);

        if (! $backup) {
            $this->error("❌ Backup no encontrado: {$argument}");
            $this->line('   Usa `php artisan ruc:backups-list` para ver los disponibles,');
            $this->line('   o pasa la ruta a un archivo .rucbackup.');

            return null;
        }

        return $backup;
    }

    private function restoreChunked(RucBackup $backup): int
    {
        $service = app(RucChunkedRestoreService::class);

        try {
            $manifest = RucBackupArchive::readManifest($backup->absolutePath());
            RucBackupArchive::assertManifestIsValid($manifest);
        } catch (\Throwable $e) {
            $this->error('❌ El backup no es válido: '.$e->getMessage());

            return self::FAILURE;
        }

        $resume = (bool) $this->option('resume');
        $operation = $resume ? $this->findResumableOperation($backup) : null;

        if ($resume && $operation === null) {
            $this->error('❌ No hay ninguna restauración reanudable para este backup.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  Archivo:   '.$backup->name);
        $this->line('  Registros: '.number_format((int) $manifest['total_records']));
        $this->line('  Lotes:     '.$manifest['total_batches'].' × '.number_format((int) $manifest['batch_size']));
        $this->line('  Formato:   '.$manifest['format'].' v'.$manifest['format_version'].' ('.$manifest['compression'].')');

        if ($resume && $operation !== null) {
            $done = (int) ($operation->checkpoint['batch'] ?? 0);
            $this->line('  Reanudar:  desde el lote '.($done + 1).' ('.number_format((int) $operation->records_processed).' registros ya cargados)');
        }

        $this->newLine();
        $this->line('  Los datos se cargan en <options=bold>'.RucChunkedRestoreService::STAGING_TABLE.'</>.');
        $this->line('  ruc_records sigue sirviendo consultas y solo se sustituye al final,');
        $this->line('  con un intercambio de nombres instantáneo. Si algún lote falla, no se toca.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('¿Continuar?', true)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $operation ??= RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_RUNNING,
            'stage' => RucBackupOperation::STAGE_RESTORING,
            'progress' => 0,
            'message' => 'Iniciando',
            'started_at' => now(),
        ]);

        if ($resume) {
            $operation->update(['status' => RucBackupOperation::STATUS_RUNNING, 'finished_at' => null]);
        }

        $bar = $this->output->createProgressBar((int) $manifest['total_batches']);
        $bar->setFormat(' %current%/%max% lotes [%bar%] %percent:3s%% %message%');
        $bar->start();
        $bar->setProgress((int) ($operation->completed_batches ?? 0));

        try {
            $result = $service->restore($backup, $operation, $resume, function (array $p) use ($bar): void {
                $bar->setMessage(sprintf(
                    '%s/%s reg · %s%% · %s reg/s · %s/s · ETA %s',
                    number_format($p['records']),
                    number_format($p['total_records']),
                    $p['percent'],
                    number_format($p['records_per_second']),
                    $this->formatBytes($p['bytes_per_second']),
                    $p['eta_seconds'] !== null ? $this->formatDuration($p['eta_seconds']) : '—'
                ));
                $bar->setProgress($p['batch']);
            });

            $bar->finish();
            $this->newLine(2);

            if ($result['cancelled'] ?? false) {
                $this->warn('⚠️  Restauración cancelada antes del lote '.$result['stopped_before_batch'].'.');
                $this->line('   ruc_records NO fue modificada.');
                $this->line('   '.$result['staging_table_kept'].' se conserva: puedes reanudar con');
                $this->line('     php artisan ruc:restore '.$backup->id.' --resume');
                $this->line('   o descartarla con:');
                $this->line('     php artisan ruc:restore-manage --discard-staging');

                return self::SUCCESS;
            }

            $this->info('✅ Restauración completada');
            $this->table(['Propiedad', 'Valor'], [
                ['Registros restaurados', number_format($result['records_restored'])],
                ['Registros anteriores', number_format($result['records_before'])],
                ['Lotes procesados', $result['batches']],
                ['Duración', $this->formatDuration($result['duration_seconds'])],
                ['Velocidad media', number_format((int) round($result['records_restored'] / max(1, $result['duration_seconds']))).' reg/s'],
                ['RAM máx. PHP', $this->formatBytes(memory_get_peak_usage(true))],
                ['Tabla anterior', $result['old_table_kept'] ? RucChunkedRestoreService::OLD_TABLE.' (conservada para rollback)' : 'eliminada'],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $bar->finish();
            $this->newLine(2);

            $operation->update([
                'status' => RucBackupOperation::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);

            $this->error('❌ Restauración fallida: '.$e->getMessage());
            $this->newLine();
            $this->line('   <options=bold>ruc_records NO fue modificada.</> Los datos se estaban cargando');
            $this->line('   en '.RucChunkedRestoreService::STAGING_TABLE.', que nunca llegó a sustituirla.');
            $this->line('   Reanuda desde el último lote confirmado con:');
            $this->line('     php artisan ruc:restore '.$backup->id.' --resume');

            return self::FAILURE;
        }
    }

    private function findResumableOperation(RucBackup $backup): ?RucBackupOperation
    {
        return RucBackupOperation::query()
            ->where('backup_id', $backup->id)
            ->where('operation_type', RucBackupOperation::TYPE_RESTORE)
            ->whereNotNull('checkpoint')
            ->where('status', '!=', RucBackupOperation::STATUS_COMPLETED)
            ->latest('id')
            ->first();
    }

    /** Camino legacy: backups .dump anteriores al formato troceado. */
    private function restoreLegacy(RucBackup $backup): int
    {
        $this->warn('⚠️  Backup en formato legacy (.dump): restauración no troceada ni reanudable.');
        $this->line("   Nombre: {$backup->name}");
        $this->line('   Registros: '.number_format($backup->total_records ?? 0));
        $this->line('   Se creará un backup de seguridad del estado actual antes de restaurar.');

        if (! $this->option('force') && ! $this->confirm('¿Deseas continuar?')) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        try {
            $this->info('Restaurando…');
            $result = app(RucBackupService::class)->restore($backup);

            $this->newLine();
            $this->info('✅ Restauración completada');
            $this->table(['Propiedad', 'Valor'], [
                ['Registros restaurados', number_format($result['records_restored'] ?? 0)],
                ['Backup de seguridad', $result['safety_backup_id']],
                ['Duración', $result['duration_seconds'].' segundos'],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Restauración fallida: '.$e->getMessage());
            $this->line('ruc_records no fue modificada (la restauración legacy es atómica).');

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

        return round($value, 1).' '.$units[$i];
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }

        return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
    }
}
