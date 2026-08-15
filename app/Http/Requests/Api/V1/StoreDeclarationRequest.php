<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la creación de una Declaración Jurada.
 *
 * Replica las reglas de la implementación oficial: documento de 8 dígitos (DNI) o
 * 9 (carné de extranjería), y al menos un bien declarado. No se confía en la
 * validación del cliente móvil.
 */
class StoreDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remitente_dni' => ['required', 'regex:/^\d{8,9}$/'],
            'remitente_nombre' => ['required', 'string', 'max:150'],
            'remitente_telefono' => ['nullable', 'string', 'max:30'],

            'destinatario_dni' => ['required', 'regex:/^\d{8,9}$/'],
            'destinatario_nombre' => ['required', 'string', 'max:150'],
            'destinatario_telefono' => ['nullable', 'string', 'max:30'],

            // La agencia debe existir en el catálogo real; el nombre que envíe el
            // cliente es indicativo y el servidor lo sustituye por el oficial.
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'sede_destino' => ['nullable', 'string', 'max:150'],

            'motivo_envio' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.cantidad' => ['nullable', 'string', 'max:20'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'remitente_dni.regex' => 'El documento del remitente debe tener 8 o 9 dígitos.',
            'destinatario_dni.regex' => 'El documento del destinatario debe tener 8 o 9 dígitos.',
            'agency_id.exists' => 'La agencia seleccionada no existe.',
            'items.required' => 'Declara al menos un bien.',
            'items.min' => 'Declara al menos un bien.',
        ];
    }
}
