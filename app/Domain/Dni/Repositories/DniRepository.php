<?php

namespace App\Domain\Dni\Repositories;

use App\Domain\Dni\Data\DniData;
use App\Models\DniRecord;

class DniRepository
{
    public function findByDni(string $dni): ?DniRecord
    {
        return DniRecord::query()->where('dni', $dni)->first();
    }

    public function updateOrCreateFromProvider(DniData $data): DniRecord
    {
        return DniRecord::query()->updateOrCreate(['dni' => $data->dni], ['nombre_completo' => $data->nombreCompleto, 'nombres' => $data->nombres, 'apellido_paterno' => $data->apellidoPaterno, 'apellido_materno' => $data->apellidoMaterno, 'fecha_nacimiento' => $data->fechaNacimiento, 'edad' => $data->edad, 'source' => 'perudevs', 'provider_reference' => $data->providerReference, 'last_verified_at' => now()]);
    }

    public function toData(DniRecord $record): DniData
    {
        return new DniData($record->dni, $record->nombre_completo, $record->nombres, $record->apellido_paterno, $record->apellido_materno, $record->fecha_nacimiento, $record->edad, $record->provider_reference);
    }
}
