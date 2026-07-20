<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Dni\Data\DniData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DniResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DniData $data */
        $data = $this->resource;

        return [
            'dni' => $data->dni,
            'nombres' => $data->nombres,
            'apellido_paterno' => $data->apellidoPaterno,
            'apellido_materno' => $data->apellidoMaterno,
            'nombre_completo' => $data->nombreCompleto,
            'genero' => $data->genero,
            'fecha_nacimiento' => $data->fechaNacimiento,
            'edad' => $data->age(),
            'codigo_verificacion' => $data->codigoVerificacion,
        ];
    }

    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
