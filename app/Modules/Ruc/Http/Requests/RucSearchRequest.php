<?php

namespace App\Modules\Ruc\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RucSearchRequest extends FormRequest
{
    public function rules(): array
    {
        return ['razon_social' => ['required', 'string', 'min:'.config('ruc.search_min_length'), 'max:150'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('ruc.search_max_results')]];
    }
}
