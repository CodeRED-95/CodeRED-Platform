<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AgencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Agency) {
            throw new LogicException('AgencyResource requiere una agencia.');
        }

        $agency = $this->resource;

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
