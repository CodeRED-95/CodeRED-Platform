<?php

namespace App\Http\Requests\Api\V1;

use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgencyIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agencia' => ['nullable', 'string', 'max:150'],
            'departamento' => ['nullable', 'string', 'max:120'],
            'provincia' => ['nullable', 'string', 'max:120'],
            'distrito' => ['nullable', 'string', 'max:120'],
            'tamano' => ['nullable', Rule::enum(AgencySize::class)],
            'co' => ['nullable', 'boolean'],
            'estado' => ['nullable', Rule::enum(AgencyStatus::class)],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::enum(AgencyStatus::class)],
            'department' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', Rule::enum(AgencySize::class)],
            'has_terrestrial' => ['nullable', 'boolean'],
            'has_air' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['code', 'name', 'external_id', 'department', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('api.max_per_page')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
