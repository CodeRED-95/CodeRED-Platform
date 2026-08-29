<?php

namespace App\Http\Requests\Api\V1\Anime;

use Illuminate\Foundation\Http\FormRequest;

final class AnimeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L}\p{N} .:\'!?&,+()-]+$/u'],
            'playable' => ['sometimes', 'boolean'],
        ];
    }
}
