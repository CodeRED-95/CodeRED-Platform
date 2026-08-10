<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatusShalomRecordarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('shalom-recordar:sync') || $this->user()?->tokenCan('shalom-recordar:read-own') ?? false;
    }

    /**
     * `installation_uuid` dejó de ser obligatorio.
     *
     * El token de sincronización se emite por instalación y queda enlazado en
     * `shalom_recordar_installations.sync_token_id`, así que el servidor puede
     * resolver la instalación a partir del propio token. Exigirlo hacía que la
     * extensión —que consultaba el estado con un GET sin parámetros— recibiera
     * un 422 en cada validación de sesión y volviera siempre al formulario de
     * login pese a tener un token válido guardado.
     */
    public function rules(): array
    {
        return [
            'installation_uuid' => ['nullable', 'string', 'uuid'],
            'extension_version' => ['nullable', 'string', 'max:40'],
        ];
    }
}
