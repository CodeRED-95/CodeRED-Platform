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
        $movedToAgency = $agency->moved_to_agency_id ? $agency->movedToAgency : null;

        return [
            'external_id' => $agency->external_id,
            'code' => $agency->code,
            'name' => $agency->name,
            'old_name' => $agency->old_name,
            'short_name' => $agency->short_name,
            'slug' => $agency->slug,
            'place' => $agency->place,
            'ubigeo_id' => $agency->ubigeo_id,
            'department' => $agency->department,
            'province' => $agency->province,
            'district' => $agency->district,
            'address' => $agency->address,
            'reference' => $agency->reference,
            'phone' => $agency->phone,
            'secondary_phone' => $agency->secondary_phone,
            'email' => $agency->email,
            'latitude' => $agency->latitude !== null ? (float) $agency->latitude : null,
            'longitude' => $agency->longitude !== null ? (float) $agency->longitude : null,
            'map_url' => $agency->map_url,
            'schedule' => [
                'general' => $agency->schedule_general ?? $agency->schedule,
                'sunday' => $agency->schedule_sunday,
            ],
            'schedule_general' => $agency->schedule_general ?? $agency->schedule,
            'schedule_sunday' => $agency->schedule_sunday,
            'classification' => [
                'category' => $agency->classification_category,
                'tamano' => $agency->classification_category,
                'sends_category' => $agency->classification_sends_category,
                'receives_category' => $agency->classification_receives_category,
            ],
            'classification_category' => $agency->classification_category,
            'classification_sends_category' => $agency->classification_sends_category,
            'classification_receives_category' => $agency->classification_receives_category,
            'chosen_terrestre' => $agency->texto_chosen_terrestre,
            'chosen_aereo' => $agency->texto_chosen_aereo,
            'status' => $agency->status->value,
            'is_operations_center' => (bool) $agency->is_operations_center,
            'has_moved' => (bool) $agency->has_moved,
            'moved_to_agency_id' => $movedToAgency?->external_id,
            'moved_to_agency_code' => $movedToAgency?->code,
            'moved_to_agency_name' => $movedToAgency?->name,
            'moved_to_address' => $agency->moved_to_address,
            'observations' => $agency->observations,
            'updated_at' => $agency->updated_at?->toIso8601String(),

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
