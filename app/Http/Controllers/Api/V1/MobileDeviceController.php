<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMobileDeviceRequest;
use App\Http\Resources\Api\V1\MobileDeviceResource;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alta y baja del dispositivo que recibe las notificaciones push.
 *
 * Sólo hace falta la ability `mobile`, como en el centro de notificaciones: un
 * dispositivo pertenece a una persona, y los tokens técnicos (n8n, el puente de
 * Declaración Jurada) no tienen a quién avisar. No se inventa un permiso RBAC
 * nuevo para algo que cada usuario hace sobre sí mismo.
 *
 * El registro es un upsert por token, no por usuario: si el mismo token ya
 * existía bajo otra cuenta, **cambia de dueño**. Eso resuelve el caso
 * incómodo de un teléfono compartido cuyo logout anterior no llegó a
 * completarse por falta de red: quien inicia sesión se lleva el token consigo y
 * el usuario anterior deja de recibir avisos en ese aparato.
 */
class MobileDeviceController
{
    public function store(StoreMobileDeviceRequest $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $token = (string) $request->validated('push_token');
        $hash = MobileDevice::hashToken($token);

        $device = DB::transaction(function () use ($request, $user, $token, $hash): MobileDevice {
            // lockForUpdate evita que dos registros simultáneos del mismo
            // teléfono —arranque y renovación de token a la vez— compitan por
            // la fila.
            $device = MobileDevice::query()->where('push_token_hash', $hash)->lockForUpdate()->first()
                ?? new MobileDevice(['push_token_hash' => $hash]);

            $device->fill([
                'user_id' => $user->getKey(),
                'platform' => $request->validated('platform'),
                'push_token' => $token,
                'device_name' => $request->validated('device_name'),
                'app_version' => $request->validated('app_version'),
                'last_seen_at' => now(),
            ]);

            $device->push_token_hash = $hash;
            $device->save();

            return $device;
        });

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo registrado.',
            'data' => new MobileDeviceResource($device),
        ], $device->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Baja del dispositivo. La consulta parte de `$user->mobileDevices()`, así
     * que el identificador de otra cuenta simplemente no se encuentra: no hay
     * comprobación de propiedad que se pueda olvidar.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $device = $user->mobileDevices()->whereKey($id)->first();

        if ($device === null) {
            // El mismo 404 exista o no en otra cuenta: el identificador no
            // confirma la existencia de dispositivos ajenos.
            return response()->json([
                'success' => false,
                'message' => 'El dispositivo no existe.',
            ], Response::HTTP_NOT_FOUND);
        }

        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo dado de baja.',
        ]);
    }

    private function currentUser(Request $request): User|JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }
}
