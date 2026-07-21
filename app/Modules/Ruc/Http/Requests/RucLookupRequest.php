<?php

namespace App\Modules\Ruc\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RucLookupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['ruc' => $this->route('ruc')]);
    }

    public function rules(): array
    {
        return ['ruc' => ['required', 'regex:/^\d{11}$/']];
    }

    public function messages(): array
    {
        return ['ruc.regex' => 'El RUC debe contener exactamente 11 dígitos.'];
    }
}
