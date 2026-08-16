<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\MobileDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta del dispositivo que recibe push.
 *
 * El token de FCM no tiene formato público garantizado, así que se valida por
 * lo que sí se puede afirmar: que es una cadena, que no viene vacía y que cabe
 * holgadamente. Poner una expresión regular sobre un formato que Google puede
 * cambiar rompería el registro sin avisar.
 */
class StoreMobileDeviceRequest extends FormRequest
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
            'push_token' => ['required', 'string', 'min:20', 'max:4096'],
            'platform' => ['required', 'string', Rule::in([MobileDevice::PLATFORM_ANDROID])],
            // Modelo comercial del aparato ("Pixel 7"), que ayuda a que el
            // usuario reconozca su propio dispositivo. Nada de identificadores
            // de hardware.
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'push_token.required' => 'Falta el token de notificaciones.',
            'platform.in' => 'Plataforma no soportada.',
        ];
    }
}
