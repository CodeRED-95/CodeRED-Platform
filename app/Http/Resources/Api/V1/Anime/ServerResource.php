<?php

namespace App\Http\Resources\Api\V1\Anime;

use App\Services\Anime\Data\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Server $server */
        $server = $this->resource;

        return $server->toArray();
    }
}
