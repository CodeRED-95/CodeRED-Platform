<?php

namespace App\Http\Requests\Agencies;

use App\Modules\Agencies\Enums\AgencyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageStatus', $this->route('agency')) ?? false;
    }

    public function rules(): array
    {
        return [
            'has_moved' => ['required', 'boolean'],
            'moved_to_agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'moved_to_address' => ['nullable', 'string'],
            'move_notice' => ['nullable', 'string'],
            'moved_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(AgencyStatus::class)],
        ];
    }
}
