<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmTokenDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo usuarios autenticados pueden confirmar entrega (admins)
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'api_token_request_id' => ['required', 'integer', 'exists:api_token_requests,id'],
            'delivery_method' => [
                'required',
                'string',
                Rule::in(['presencial', 'llamada', 'canal_corporativo', 'otro']),
            ],
            'delivery_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_method.required' => 'El método de entrega es requerido.',
            'delivery_method.in' => 'El método de entrega no es válido.',
            'delivery_reason.max' => 'La razón de entrega no debe exceder 500 caracteres.',
        ];
    }
}
