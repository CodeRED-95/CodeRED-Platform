<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatusShalomRecordarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('shalom-recordar:sync') ?? false;
    }

    public function rules(): array
    {
        return [
            'installation_uuid' => ['required', 'string', 'uuid'],
            'extension_version' => ['nullable', 'string', 'max:40'],
        ];
    }
}
