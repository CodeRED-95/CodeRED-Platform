<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Console\Command;

class RucRestoreCommand extends Command
{
    protected $signature = 'ruc:restore {backup_id : ID del backup a restaurar}';

    protected $description = 'Restaurar ruc_records desde un backup (crea un safety backup automáticamente antes)';

    public function handle(): int
    {
        $backupId = $this->argument('backup_id');

        $backup = RucBackup::find($backupId);
        if (! $backup) {
            $this->error("❌ Backup no encontrado: {$backupId}");

            return self::FAILURE;
        }

        if (! $backup->isCompleted()) {
            $this->error("❌ El backup debe estar completado. Estado: {$backup->status}");

            return self::FAILURE;
        }

        $this->warn("⚠️  ADVERTENCIA: esto reemplazará TODOS los registros actuales de ruc_records con el contenido del backup #{$backupId}");
        $this->line("   Nombre: {$backup->name}");
        $this->line('   Registros: '.number_format($backup->total_records ?? 0));
        $this->line("   Tamaño: {$backup->formattedSize()}");
        $this->line("   Creado: {$backup->created_at->format('Y-m-d H:i:s')}");
        $this->line('   Se creará un backup de seguridad del estado actual antes de restaurar.');

        if (! $this->confirm('¿Deseas continuar?')) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        try {
            $this->info('Restaurando...');
            $result = app(RucBackupService::class)->restore($backup);

            $this->newLine();
            $this->info('✅ Restauración completada exitosamente');
            $this->table(
                ['Propiedad', 'Valor'],
                [
                    ['Backup restaurado', $backup->id],
                    ['Registros restaurados', number_format($result['records_restored'] ?? 0)],
                    ['Backup de seguridad', $result['safety_backup_id']],
                    ['Duración', $result['duration_seconds'].' segundos'],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Restauración fallida: '.$e->getMessage());
            $this->line('ruc_records no fue modificada (la restauración es atómica: revierte todo o nada).');

            return self::FAILURE;
        }
    }
}
