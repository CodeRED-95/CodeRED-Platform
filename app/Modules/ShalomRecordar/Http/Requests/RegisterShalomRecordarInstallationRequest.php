<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterShalomRecordarInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'installation_uuid' => ['required', 'string', 'uuid'],
            'extension_version' => ['required', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'browser_name' => ['nullable', 'string', 'max:80'],
            'browser_version' => ['nullable', 'string', 'max:40'],
            'platform_name' => ['nullable', 'string', 'max:80'],
            'platform_version' => ['nullable', 'string', 'max:40'],
        ];
    }
}
