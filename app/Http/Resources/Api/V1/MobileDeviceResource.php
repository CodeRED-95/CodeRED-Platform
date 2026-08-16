<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\MobileDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MobileDevice
 *
 * Lo que la app necesita saber de su propio registro: su identificador, para
 * poder darse de baja al cerrar sesión, y poco más.
 *
 * El token **no aparece aquí a propósito**. El cliente ya lo tiene —se lo dio
 * Firebase— y devolvérselo sólo añadiría una copia más viajando por la red y
 * quedando en cachés intermedias.
 */
class MobileDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'platform' => $this->platform,
            'device_name' => $this->device_name,
            'app_version' => $this->app_version,
            'ultima_actividad' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
