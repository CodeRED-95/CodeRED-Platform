<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Models\Agency;

final class AgencySyncPayload
{
    /** @return array<string, mixed> */
    public function fromAgency(Agency $agency): array
    {
        return [
            'internal_id' => (int) $agency->getKey(),
            'id' => $agency->external_id,
            'code' => $agency->code,
            'agencia' => trim($agency->name),
            'departamento' => trim($agency->department),
            'provincia' => trim($agency->province),
            'distrito' => trim($agency->district),
            'direccion' => trim($agency->address),
            'link_mapa' => $agency->map_url,
            'tamano' => $agency->size?->label(),
            'texto_chosen_terrestre' => $agency->texto_chosen_terrestre,
            'texto_chosen_aereo' => $agency->texto_chosen_aereo,
        ];
    }
}
