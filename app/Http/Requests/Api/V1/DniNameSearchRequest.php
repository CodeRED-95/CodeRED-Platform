<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class DniNameSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L} .\'-]+$/u'],
            'apellido_paterno' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L} .\'-]+$/u'],
            'apellido_materno' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L} .\'-]+$/u'],
        ];
    }
}
