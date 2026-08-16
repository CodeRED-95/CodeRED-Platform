<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centro de notificaciones de CodeRED Mobile.
 *
 * Trabaja sobre el canal `database` de Laravel Notifications: no hay tabla ni
 * estado propios. Todas las consultas parten de `$user->notifications()`, así
 * que un usuario no puede alcanzar las de otro ni conociendo su identificador
 * —el UUID simplemente no aparece en su relación—.
 */
class NotificationController
{
    /** Historial del usuario, más recientes primero. */
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $perPage = min(max((int) $request->integer('per_page', 20), 1), (int) config('api.max_per_page'));

        $response = NotificationResource::collection(
            $user->notifications()->latest()->paginate($perPage)->withQueryString()
        )->response();

        $payload = $response->getData(true);
        $payload['success'] = true;
        // El contador viaja con la lista para que la pantalla pinte el badge sin
        // una segunda petición.
        $payload['meta']['no_leidas'] = $user->unreadNotifications()->count();
        $response->setData($payload);

        return $response;
    }

    /** Sólo el contador: lo consulta el Dashboard, que no necesita la lista. */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json([
            'success' => true,
            'data' => ['no_leidas' => $user->unreadNotifications()->count()],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $notification = $user->notifications()->whereKey($id)->first();

        if ($notification === null) {
            // Mismo 404 exista o no en otra cuenta: no se confirma la
            // existencia de notificaciones ajenas.
            return response()->json([
                'success' => false,
                'message' => 'La notificación no existe.',
            ], Response::HTTP_NOT_FOUND);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'data' => ['no_leidas' => $user->unreadNotifications()->count()],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $marcadas = $user->unreadNotifications()->count();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notificaciones marcadas como leídas.',
            'data' => ['marcadas' => $marcadas, 'no_leidas' => 0],
        ]);
    }

    /**
     * Las notificaciones son de una persona, no de un servicio: un token
     * técnico no tiene ninguna que leer.
     */
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
