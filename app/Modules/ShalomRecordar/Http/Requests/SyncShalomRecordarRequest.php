<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncShalomRecordarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('shalom-recordar:sync') ?? false;
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
