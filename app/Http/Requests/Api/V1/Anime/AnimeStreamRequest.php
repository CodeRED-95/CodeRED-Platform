<?php

namespace App\Http\Requests\Api\V1\Anime;

use Illuminate\Foundation\Http\FormRequest;

final class AnimeStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server' => ['required', 'string', 'min:1', 'max:120', 'regex:/^[a-zA-Z0-9._:-]+$/'],
        ];
    }
}
