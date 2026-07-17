<?php

namespace App\Modules\Agencies\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'status_label' => $this->status?->label() ?? null,
            'source' => $this->source,
            'source_reference' => $this->source_reference,
            'data_version' => $this->data_version,
            'last_verified_at' => optional($this->last_verified_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
