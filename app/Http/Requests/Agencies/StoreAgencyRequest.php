<?php

namespace App\Http\Requests\Agencies;

use App\Modules\Agencies\Enums\AgencyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Modules\Agencies\Models\Agency::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:agencies,code'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'reference' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'schedule' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'services' => ['required', 'array'],
            'services.*' => ['string', 'max:255'],
            'observations' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(AgencyStatus::class)],
            'source' => ['required', 'string', 'max:255'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'last_verified_at' => ['nullable', 'date'],
        ];
    }
}
