<?php

namespace App\Http\Resources\Api\V1\Anime;

use App\Services\Anime\Data\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EpisodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Episode $episode */
        $episode = $this->resource;

        return $episode->toArray();
    }
}
