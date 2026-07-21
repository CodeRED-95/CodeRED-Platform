<?php

namespace App\Console\Commands;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RucCleanupImportsCommand extends Command
{
    protected $signature = 'ruc:cleanup-imports {--days=} {--dry-run}';

    protected $description = 'Elimina archivos fuente RUC antiguos y conserva su historial';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('ruc.import_retention_days')));
        $imports = RucImport::query()
            ->whereIn('status', [RucImportStatus::Completed, RucImportStatus::CompletedWithErrors, RucImportStatus::Failed, RucImportStatus::Cancelled])
            ->where('created_at', '<', now()->subDays($days))->where('path', '!=', 'deleted')->cursor();
        $count = 0;
        foreach ($imports as $import) {
            $count++;
            if (! $this->option('dry-run')) {
                Storage::disk($import->disk)->delete($import->path);
                $import->update(['path' => 'deleted']);
            }
        }
        $this->info(($this->option('dry-run') ? 'Archivos candidatos: ' : 'Archivos eliminados: ').$count);

        return self::SUCCESS;
    }
}
