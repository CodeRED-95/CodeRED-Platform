<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucBackup;
use Illuminate\Console\Command;

class RucListBackupsCommand extends Command
{
    protected $signature = 'ruc:backups-list {--status= : Filtrar por estado (completed, creating, failed)}';

    protected $description = 'Listar todos los backups de ruc_records';

    public function handle(): int
    {
        $status = $this->option('status');

        $backups = RucBackup::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('created_at')
            ->get();

        if ($backups->isEmpty()) {
            $this->info('No hay backups.');

            return self::SUCCESS;
        }

        $rows = $backups->map(function (RucBackup $backup) {
            $statusEmoji = match ($backup->status) {
                RucBackup::STATUS_COMPLETED => '✅',
                RucBackup::STATUS_FAILED => '❌',
                RucBackup::STATUS_CREATING => '⏳',
                default => '❓',
            };

            return [
                $backup->id,
                $statusEmoji.' '.$backup->status,
                $backup->name,
                $backup->backup_type ?? '-',
                number_format($backup->total_records ?? 0),
                $backup->formattedSize(),
                $backup->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Estado', 'Nombre', 'Tipo', 'Registros', 'Tamaño', 'Creado'],
            $rows
        );

        $this->newLine();
        $this->info("Total: {$backups->count()} backups");

        $completed = $backups->where('status', RucBackup::STATUS_COMPLETED)->count();
        $failed = $backups->where('status', RucBackup::STATUS_FAILED)->count();
        $this->line("✅ Completados: {$completed} | ❌ Fallidos: {$failed}");

        return self::SUCCESS;
    }
}
