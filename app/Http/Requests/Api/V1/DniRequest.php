<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['dni' => $this->route('dni')]);
    }

    public function rules(): array
    {
        return ['dni' => ['required', 'regex:/^\d{8}$/']];
    }

    public function messages(): array
    {
        return ['dni.regex' => 'El DNI debe contener exactamente 8 dígitos numéricos.'];
    }
}
