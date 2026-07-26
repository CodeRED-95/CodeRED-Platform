<?php

namespace App\Http\Resources\Api;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AgencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $agency = $this->resource;

        if (! $agency instanceof Agency) {
            throw new LogicException('AgencyResource requiere una instancia de Agency.');
        }

        return [
            'external_id' => $agency->external_id,
            'internal_id' => $agency->id,
            'code' => $agency->code,
            'name' => $agency->name,
            'old_name' => $agency->old_name,
            'place' => $agency->place,
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
                'tamano' => $agency->classification_category,
                'sends_category' => $agency->classification_sends_category,
                'receives_category' => $agency->classification_receives_category,
            ],
            'chosen_terrestre' => $agency->texto_chosen_terrestre,
            'chosen_aereo' => $agency->texto_chosen_aereo,
            'status' => $agency->status->value,
            'estado' => $agency->status->label(),
            'centro_operaciones' => (bool) $agency->is_operations_center,
        ];
    }
}
