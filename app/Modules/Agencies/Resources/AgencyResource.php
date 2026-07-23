<?php

namespace App\Modules\Agencies\Resources;

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

        $move = null;

        if ((bool) ($agency->has_moved ?? false)) {
            $move = [
                'moved_at' => optional($agency->moved_at)?->toDateString(),
                'notice' => $agency->move_notice,
                'destination_agency' => $agency->movedToAgency ? [
                    'code' => $agency->movedToAgency->code,
                    'name' => $agency->movedToAgency->name,
                    'address' => $agency->movedToAgency->address,
                    'url' => url('/api/v1/agencies/'.$agency->movedToAgency->code),
                ] : null,
                'destination_address' => $agency->moved_to_address,
            ];
        }

        return [
            'internal_id' => $agency->id,
            'id' => $agency->external_id,
            'code' => $agency->code,
            'texto_chosen_terrestre' => $agency->texto_chosen_terrestre,
            'texto_chosen_aereo' => $agency->texto_chosen_aereo,
            'texto_chosen' => $agency->legacyChosenText(),
            'agencia' => trim($agency->name),
            'departamento' => trim($agency->department),
            'provincia' => trim($agency->province),
            'distrito' => trim($agency->district),
            'direccion' => trim($agency->address),
            'link_mapa' => $agency->map_url,
            'tamano' => $agency->size?->label(),
            'estado' => $agency->status->label(),
            'centro_operaciones' => (bool) $agency->is_operations_center,
            'name' => $agency->name,
            'short_name' => $agency->short_name,
            'slug' => $agency->slug,
            'department' => $agency->department,
            'province' => $agency->province,
            'district' => $agency->district,
            'address' => $agency->address,
            'reference' => $agency->reference,
            'phone' => $agency->phone,
            'secondary_phone' => $agency->secondary_phone,
            'email' => $agency->email,
            'schedule' => $agency->schedule,
            'latitude' => $agency->latitude,
            'longitude' => $agency->longitude,
            'map_url' => $agency->map_url,
            'services' => $agency->services ?? [],
            'size' => $agency->size?->value,
            'category' => $agency->category->value,
            'category_limitations' => $agency->category->limitations(),
            'status' => $agency->status->value,
            'has_moved' => (bool) ($agency->has_moved ?? false),
            'is_operations_center' => (bool) ($agency->is_operations_center ?? false),
            'move' => $move,
            'status_label' => $agency->status->label(),
            'source' => $agency->source,
            'source_reference' => $agency->source_reference,
            'data_version' => $agency->data_version,
            'last_verified_at' => optional($agency->last_verified_at)?->toIso8601String(),
            'updated_at' => optional($agency->updated_at)?->toIso8601String(),
        ];
    }
}
