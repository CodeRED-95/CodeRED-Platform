<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'backup_file' => [
                'required',
                'file',
                'mimes:gz',
                'max:5242880', // 5 GB máximo
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'backup_file.required' => 'Debes seleccionar un archivo de backup',
            'backup_file.file' => 'El archivo debe ser válido',
            'backup_file.mimes' => 'El archivo debe ser un .gz (comprimido)',
            'backup_file.max' => 'El archivo no puede superar 5 GB',
        ];
    }
}
