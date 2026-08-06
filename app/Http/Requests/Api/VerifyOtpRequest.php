<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización pública (validado por tracking_code + email)
    }

    public function rules(): array
    {
        return [
            'api_token_request_id' => ['required', 'integer', 'exists:api_token_requests,id'],
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código OTP es requerido.',
            'code.size' => 'El código OTP debe tener 6 dígitos.',
            'code.regex' => 'El código OTP debe contener solo números.',
            'api_token_request_id.required' => 'ID de solicitud requerido.',
            'api_token_request_id.exists' => 'La solicitud no existe.',
        ];
    }
}
