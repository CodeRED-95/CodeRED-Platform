<?php

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucRecord;
use App\Modules\Ruc\Models\Ubigeo;
use App\Modules\Ruc\Support\RucAddressBuilder;
use Illuminate\Console\Command;

class RucRebuildAddressesCommand extends Command
{
    protected $signature = 'ruc:rebuild-addresses {--dry-run} {--only-missing}';

    protected $description = 'Reconstruye direcciones y geografía de registros RUC existentes';

    public function handle(RucAddressBuilder $builder): int
    {
        $locations = Ubigeo::query()->get()->keyBy('codigo');
        $query = RucRecord::query()->orderBy('id');
        if ($this->option('only-missing')) {
            $query->where(fn ($query) => $query->whereNull('direccion')->orWhereNull('departamento'));
        }
        $updated = 0;
        $query->chunkById(1000, function ($records) use ($builder, $locations, &$updated): void {
            foreach ($records as $record) {
                $address = $builder->build([
                    $record->tipo_via, $record->nombre_via, $record->codigo_zona, $record->tipo_zona,
                    $record->numero, $record->interior, $record->lote, $record->departamento_direccion,
                    $record->manzana, $record->kilometro,
                ]);
                $location = $record->ubigeo === null ? null : $locations->get($record->ubigeo);
                $values = ['direccion' => $address];
                if ($location !== null) {
                    $values += ['departamento' => $location->departamento, 'provincia' => $location->provincia, 'distrito' => $location->distrito];
                }
                if (! $this->option('dry-run') && $record->only(array_keys($values)) !== $values) {
                    $record->update($values);
                }
                $updated++;
            }
        });
        $this->info(($this->option('dry-run') ? 'Simulados' : 'Procesados').": {$updated} registros.");

        return self::SUCCESS;
    }
}
