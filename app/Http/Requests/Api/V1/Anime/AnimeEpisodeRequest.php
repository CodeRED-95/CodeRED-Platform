<?php

namespace App\Http\Requests\Api\V1\Anime;

use Illuminate\Foundation\Http\FormRequest;

final class AnimeEpisodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
