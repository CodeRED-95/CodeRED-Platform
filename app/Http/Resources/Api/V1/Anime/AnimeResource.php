<?php

namespace App\Http\Resources\Api\V1\Anime;

use App\Services\Anime\Data\Anime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AnimeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Anime $anime */
        $anime = $this->resource;

        return $anime->toArray();
    }
}
