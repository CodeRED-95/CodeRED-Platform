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

            // El destinatario es opcional en su totalidad: quien envia un
            // paquete no siempre sabe quien lo va a recoger, y el formato
            // oficial admite esos campos en blanco.
            'destinatario_dni' => ['nullable', 'regex:/^\d{8,9}$/'],
            'destinatario_nombre' => ['nullable', 'string', 'max:150'],
            'destinatario_telefono' => ['nullable', 'string', 'max:30'],

            // La agencia debe existir en el catálogo real; el nombre que envíe el
            // cliente es indicativo y el servidor lo sustituye por el oficial.
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'sede_destino' => ['nullable', 'string', 'max:150'],

            'motivo_envio' => ['nullable', 'string', 'max:255'],

            // Los bienes tambien son opcionales, y como maximo tres: son las
            // filas que tiene la tabla del formato oficial.
            'items' => ['nullable', 'array', 'max:3'],
            'items.*.cantidad' => ['nullable', 'string', 'max:20'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],

            // Foto del DNI. Su presencia es lo que decide la orientacion del
            // documento: sin ella A4 vertical, con ella A4 horizontal.
            'foto_dni' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            'remitente_dni.regex' => 'El documento del remitente debe tener 8 o 9 dígitos.',
            'agency_id.exists' => 'La agencia seleccionada no existe.',
            'items.max' => 'El formato oficial admite como máximo tres bienes.',
            'foto_dni.image' => 'La foto del DNI debe ser una imagen.',
        ];
    }
}
