<?php

namespace App\Http\Resources\Api\V1\Anime;

use App\Services\Anime\Data\Stream;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StreamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Stream $stream */
        $stream = $this->resource;

        return [
            ...$stream->toArray(),
            'headers' => (object) $stream->headers,
        ];
    }
}
