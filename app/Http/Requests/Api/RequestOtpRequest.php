<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Público
    }

    public function rules(): array
    {
        return [
            'api_token_request_id' => ['required', 'integer', 'exists:api_token_requests,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'api_token_request_id.required' => 'ID de solicitud requerido.',
            'api_token_request_id.exists' => 'La solicitud no existe.',
        ];
    }
}
