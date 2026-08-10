<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class SyncShalomRecordarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('shalom-recordar:sync') ?? false;
    }

    /**
     * Registra QUÉ campos fallaron, nunca sus valores.
     *
     * Ayuda a diagnosticar un 422 sin filtrar datos sensibles: solo se anotan
     * las claves inválidas (p. ej. `records.0.timestamp`) y el conteo, jamás el
     * token, el valor del registro ni cabeceras.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::channel(config('logging.default'))->info('shalom-recordar sync validation failed', [
            'user_id' => $this->user()?->getAuthIdentifier(),
            'installation_uuid' => $this->input('installation_uuid'),
            'extension_version' => $this->input('extension_version'),
            'invalid_fields' => array_keys($validator->errors()->toArray()),
            'records_received' => is_array($this->input('records')) ? count($this->input('records')) : 0,
        ]);

        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return [
            'installation_uuid' => ['required', 'string', 'uuid'],
            'extension_version' => ['required', 'string', 'max:40'],
            'batch_id' => ['nullable', 'string', 'max:120'],
            'cursor' => ['nullable', 'string', 'max:120'],
            'installation' => ['nullable', 'array'],
            'installation.device_name' => ['nullable', 'string', 'max:120'],
            'installation.browser_name' => ['nullable', 'string', 'max:80'],
            'installation.browser_version' => ['nullable', 'string', 'max:40'],
            'installation.platform_name' => ['nullable', 'string', 'max:80'],
            'installation.platform_version' => ['nullable', 'string', 'max:40'],
            'records' => ['required', 'array', 'max:500'],
            'records.*.field' => ['required', 'string', 'max:100'],
            'records.*.value' => ['required', 'string', 'max:2000'],
            'records.*.timestamp' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
            'records.*.record_id' => ['nullable', 'string', 'max:120'],
            'records.*.id' => ['nullable', 'string', 'max:120'],
            'records.*.cursor' => ['nullable', 'string', 'max:120'],
        ];
    }
}
