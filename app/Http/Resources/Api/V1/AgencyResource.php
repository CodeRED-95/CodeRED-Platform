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
            'external_id' => $agency->external_id,
            'code' => $agency->code,
            'name' => $agency->name,
            'old_name' => $agency->old_name,
            'place' => $agency->place,
            'zone' => $agency->zone,
            'department' => $agency->department,
            'province' => $agency->province,
            'district' => $agency->district,
            'address' => $agency->address,
            'latitude' => $agency->latitude !== null ? (float) $agency->latitude : null,
            'longitude' => $agency->longitude !== null ? (float) $agency->longitude : null,
            'map_url' => $agency->map_url,
            'schedule' => [
                'general' => $agency->schedule_general ?? $agency->schedule,
                'sunday' => $agency->schedule_sunday,
            ],
            'classification' => [
                'category' => $agency->classification_category,
                'sends_category' => $agency->classification_sends_category,
                'receives_category' => $agency->classification_receives_category,
            ],
            'chosen_terrestre' => $agency->texto_chosen_terrestre,
            'chosen_aereo' => $agency->texto_chosen_aereo,
            'status' => $agency->status->value,

            // Compatibilidad temporal con consumidores anteriores.
            'internal_id' => (int) $agency->getKey(),
            'id' => $agency->external_id,
            'agencia' => trim((string) $agency->name),
            'agencia_anterior' => $agency->old_name,
            'departamento' => $agency->department,
            'provincia' => $agency->province,
            'distrito' => $agency->district,
            'direccion' => $agency->address,
            'link_mapa' => $agency->map_url,
            'tamano' => $agency->size?->label(),
            'estado' => $agency->status->label(),
            'centro_operaciones' => (bool) $agency->is_operations_center,
            'texto_chosen_terrestre' => $agency->texto_chosen_terrestre,
            'texto_chosen_aereo' => $agency->texto_chosen_aereo,
        ];
    }
}
