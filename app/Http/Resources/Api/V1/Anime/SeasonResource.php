<?php

namespace App\Http\Resources\Api\V1\Anime;

use App\Services\Anime\Data\Season;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SeasonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Season $season */
        $season = $this->resource;

        return $season->toArray();
    }
}
