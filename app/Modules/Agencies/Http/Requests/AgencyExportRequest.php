<?php

namespace App\Modules\Agencies\Http\Requests;

use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgencyExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('agencies.export') === true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['filtered', 'all'])],
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(AgencyStatus::class)],
            'department' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', Rule::enum(AgencySize::class)],
            'source' => ['nullable', 'string', 'max:50'],
            'operations_center' => ['nullable', Rule::in(['0', '1'])],
            'moved' => ['nullable', Rule::in(['0', '1'])],
            'without_coordinates' => ['nullable', Rule::in(['0', '1'])],
            'without_phone' => ['nullable', Rule::in(['0', '1'])],
            'under_review' => ['nullable', Rule::in(['0', '1'])],
            'trash' => ['nullable', Rule::in(['', 'only', 'with'])],
        ];
    }
}
