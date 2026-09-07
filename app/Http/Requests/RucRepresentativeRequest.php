<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RucRepresentativeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['ruc' => $this->route('ruc')]);
    }

    public function rules(): array
    {
        return ['ruc' => [
            'required',
            'regex:/^\d{11}$/',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! preg_match('/^(10|15|17|20)\d{9}$/', (string) $value)) {
                    $fail('La estructura del RUC no es válida.');
                }
            },
        ]];
    }

    public function messages(): array
    {
        return ['ruc.regex' => 'El RUC debe contener exactamente 11 dígitos.'];
    }
}
