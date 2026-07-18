<?php

namespace App\Modules\Agencies\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $move = null;

        if ((bool) ($this->has_moved ?? false)) {
            $move = [
                'moved_at' => optional($this->moved_at)?->toDateString(),
                'notice' => $this->move_notice,
                'destination_agency' => $this->movedToAgency ? [
                    'code' => $this->movedToAgency->code,
                    'name' => $this->movedToAgency->name,
                    'address' => $this->movedToAgency->address,
                    'url' => url('/api/v1/agencies/'.$this->movedToAgency->code),
                ] : null,
                'destination_address' => $this->moved_to_address,
            ];
        }

        return [
            'code' => $this->code,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'slug' => $this->slug,
            'department' => $this->department,
            'province' => $this->province,
            'district' => $this->district,
            'address' => $this->address,
            'reference' => $this->reference,
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'email' => $this->email,
            'schedule' => $this->schedule,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'services' => $this->services ?? [],
            'status' => $this->status?->value ?? $this->status,
            'has_moved' => (bool) ($this->has_moved ?? false),
            'is_operations_center' => (bool) ($this->is_operations_center ?? false),
            'move' => $move,
            'status_label' => $this->status?->label() ?? null,
            'source' => $this->source,
            'source_reference' => $this->source_reference,
            'data_version' => $this->data_version,
            'last_verified_at' => optional($this->last_verified_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
