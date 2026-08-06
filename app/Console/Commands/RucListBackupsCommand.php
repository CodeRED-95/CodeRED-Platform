<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucBackup;
use Illuminate\Console\Command;

class RucListBackupsCommand extends Command
{
    protected $signature = 'ruc:backups-list {--status= : Filtrar por estado (completed, failed, pending)}';

    protected $description = 'Listar todos los backups de RUC';

    public function handle(): int
    {
        $status = $this->option('status');

        $query = RucBackup::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('created_at');

        $backups = $query->get();

        if ($backups->isEmpty()) {
            $this->info('No hay backups.');
            return 0;
        }

        $rows = $backups->map(function (RucBackup $backup) {
            $statusEmoji = match ($backup->status) {
                'completed' => '✅',
                'failed' => '❌',
                'pending' => '⏳',
                'deleted' => '🗑️',
                default => '❓',
            };

            return [
                $backup->id,
                $statusEmoji . ' ' . $backup->status,
                $backup->name,
                $backup->backup_type,
                number_format($backup->total_records ?? 0),
                $backup->getFormattedSize(),
                $backup->storage_type,
                $backup->created_at->format('Y-m-d H:i'),
                $backup->duration_seconds ? $backup->duration_seconds . 's' : '-',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Status', 'Nombre', 'Tipo', 'Registros', 'Tamaño', 'Almacenamiento', 'Creado', 'Duración'],
            $rows
        );

        $this->newLine();
        $this->info("Total: {$backups->count()} backups");

        $completed = $backups->where('status', 'completed')->count();
        $failed = $backups->where('status', 'failed')->count();
        $this->line("✅ Completados: {$completed} | ❌ Fallidos: {$failed}");

        return 0;
    }
}
