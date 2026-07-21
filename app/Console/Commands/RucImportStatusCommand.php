<?php

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucImport;
use Illuminate\Console\Command;

class RucImportStatusCommand extends Command
{
    protected $signature = 'ruc:import-status {uuid?}';

    protected $description = 'Muestra el estado persistido de importaciones RUC';

    public function handle(): int
    {
        $query = RucImport::query()->latest();
        if ($this->argument('uuid')) {
            $query->where('uuid', $this->argument('uuid'));
        }
        $this->table(['UUID', 'Estado', 'Progreso', 'Procesadas', 'Insertadas', 'Ignoradas', 'Inválidas'], $query->limit(20)->get()->map(fn (RucImport $import): array => [$import->uuid, $import->status->value, $import->progress_percentage.'%', $import->processed_rows, $import->inserted_rows, $import->ignored_rows, $import->invalid_rows]));

        return self::SUCCESS;
    }
}
