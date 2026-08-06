<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShalomSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La extensión no tiene autenticación formal; aceptar desde cualquier origen
        // En producción: implementar API key o Bearer token
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'records' => ['required', 'array', 'max:500'],
            'records.*.field' => ['required', 'string', 'in:DNI,CE,RUC,OS,Clave'],
            'records.*.value' => ['required', 'string', 'max:1000'],
            'records.*.timestamp' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'records.max' => 'No more than 500 records per sync',
            'records.*.field.in' => 'Field must be one of: DNI, CE, RUC, OS, Clave',
            'records.*.timestamp.date_format' => 'Timestamp must be in ISO 8601 format (Y-m-d\TH:i:s\Z)',
        ];
    }
}
