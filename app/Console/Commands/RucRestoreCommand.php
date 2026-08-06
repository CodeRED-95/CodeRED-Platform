<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Console\Command;

class RucRestoreCommand extends Command
{
    protected $signature = 'ruc:restore {backup_id : ID del backup a restaurar} {--dry-run : Simular restauración sin hacer cambios}';

    protected $description = 'Restaurar tabla ruc_records desde un backup';

    public function handle(): int
    {
        $backupId = $this->argument('backup_id');
        $dryRun = $this->option('dry-run');

        $backup = RucBackup::find($backupId);
        if (!$backup) {
            $this->error("❌ Backup no encontrado: {$backupId}");
            return 1;
        }

        if ($backup->status !== 'completed') {
            $this->error("❌ El backup debe estar completado. Estado: {$backup->status}");
            return 1;
        }

        $this->warn("⚠️  ADVERTENCIA: Esto va a restaurar los datos del backup #{$backupId}");
        $this->line("   Nombre: {$backup->name}");
        $this->line("   Registros: " . number_format($backup->total_records ?? 0));
        $this->line("   Tamaño: {$backup->getFormattedSize()}");
        $this->line("   Creado: {$backup->created_at->format('Y-m-d H:i:s')}");

        if ($dryRun) {
            $this->line("   🔍 MODO DRY-RUN (sin hacer cambios)");
        } else {
            $this->line("   ⚡ MODO REAL (se harán cambios)");
        }

        if (!$this->confirm('¿Deseas continuar?')) {
            $this->info('Cancelado.');
            return 0;
        }

        try {
            $this->info("Restaurando backup...");
            $result = (new RucBackupService())->restore($backup, $dryRun);

            $this->newLine();
            $this->info('✅ Restauración completada exitosamente');
            $this->table(
                ['Propiedad', 'Valor'],
                [
                    ['Backup ID', $result['backup_id']],
                    ['Registros restaurados', number_format($result['records_restored'] ?? 0)],
                    ['Duración', $result['duration_seconds'] . ' segundos'],
                    ['Modo', $dryRun ? 'Dry-run (sin cambios)' : 'Real (datos cambiados)'],
                ]
            );

            return 0;

        } catch (\Throwable $e) {
            $this->error('❌ Restauración fallida: ' . $e->getMessage());
            return 1;
        }
    }
}
