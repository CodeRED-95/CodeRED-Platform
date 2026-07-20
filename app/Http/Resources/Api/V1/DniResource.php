<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Dni\Data\DniData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DniResource extends JsonResource
{
    public function toArray(Request $request): array
    { /** @var DniData $data */ $data = $this->resource;

        return $data->toArray();
    }

    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
