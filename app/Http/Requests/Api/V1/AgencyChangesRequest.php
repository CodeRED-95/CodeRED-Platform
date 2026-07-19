<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AgencyChangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cursor' => ['required', 'string', 'max:1024'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('api.agency_changes_max_limit')],
        ];
    }
}
