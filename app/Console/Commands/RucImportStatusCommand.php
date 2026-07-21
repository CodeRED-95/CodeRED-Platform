<?php

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RucImportStatusCommand extends Command
{
    protected $signature = 'ruc:import-status {uuid?} {--id=}';

    protected $description = 'Muestra el estado persistido de importaciones RUC';

    public function handle(): int
    {
        $query = RucImport::query()->latest();
        if ($this->argument('uuid')) {
            $query->where('uuid', $this->argument('uuid'));
        }
        if ($this->option('id')) {
            $query->whereKey((int) $this->option('id'));
        }
        $this->table(
            ['ID', 'Archivo', 'Estado', 'Cola / Job', 'Total', 'Procesadas', 'Nuevas', 'Existentes', 'Inválidas', 'Heartbeat', 'Archivo', 'Disk / path', 'Mensaje / error'],
            $query->limit(20)->get()->map(fn (RucImport $import): array => [
                $import->id,
                $import->original_filename,
                $import->status->label().' · '.$import->progress_percentage.'%',
                $import->queue_name.' / '.($import->job_uuid ?? 'pendiente'),
                $import->total_rows,
                $import->processed_rows,
                $import->inserted_rows,
                $import->ignored_rows,
                $import->invalid_rows,
                $import->last_heartbeat_at?->toDateTimeString() ?? 'sin actividad',
                Storage::disk($import->disk)->exists($import->path) ? 'sí' : 'no',
                $import->disk.' / '.$import->path,
                $import->error_message ?: $import->last_message,
            ])
        );

        return self::SUCCESS;
    }
}
