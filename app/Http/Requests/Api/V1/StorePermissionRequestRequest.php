<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Permissions\MobileAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de acceso a un módulo móvil.
 *
 * La regla que importa es la primera: el permiso debe estar en la lista blanca
 * de <see cref="MobileAccess"/>. Sin ella, quien manipulara la petición podría
 * pedir `users.delete` y dejar a un administrador a un clic de concedérselo.
 */
class StorePermissionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permission' => ['required', 'string', Rule::in(MobileAccess::requestable())],

            // El motivo ayuda a quien revisa, pero obligarlo sólo produciría
            // textos de relleno. Se acota y se limpia: esto acaba en una
            // pantalla de administración.
            'reason' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (is_string($reason)) {
            // Nada de marcado: el motivo es texto plano y así se guarda.
            $this->merge(['reason' => trim(strip_tags($reason))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permission.required' => 'Indica a qué acceso quieres solicitar permiso.',
            'permission.in' => 'Ese acceso no se puede solicitar desde la aplicación.',
            'reason.max' => 'El motivo no puede superar los 300 caracteres.',
        ];
    }
}
