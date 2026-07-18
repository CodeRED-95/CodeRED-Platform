<?php

namespace App\Http\Requests\Agencies;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;

class PreviewAgencyImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', Agency::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
        ];
    }
}
