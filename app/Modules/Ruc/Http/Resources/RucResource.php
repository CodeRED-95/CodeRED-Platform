<?php

namespace App\Modules\Ruc\Http\Resources;

use App\Modules\Ruc\Data\RucData;
use App\Modules\Ruc\Models\RucRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RucResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        if ($resource instanceof RucData) {
            return $resource->toArray();
        }
        abort_unless($resource instanceof RucRecord, 500);

        return [
            'ruc' => $resource->ruc, 'razon_social' => $resource->razon_social, 'estado' => $resource->estado,
            'condicion' => $resource->condicion, 'ubigeo' => $resource->ubigeo, 'direccion' => $resource->direccion,
            'departamento' => $resource->departamento, 'provincia' => $resource->provincia, 'distrito' => $resource->distrito,
        ];
    }
}
