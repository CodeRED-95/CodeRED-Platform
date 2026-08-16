<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base de los controladores de administración móvil.
 *
 * Los dos ejes de siempre: el middleware `abilities:` comprueba que el token
 * alcanza el área, y aquí se comprueba el permiso RBAC de la acción concreta.
 * Tener la ability no basta —un token viejo podría conservarla después de que a
 * la persona le retiraran el permiso—, así que el permiso se consulta contra la
 * base en cada petición.
 *
 * Devuelve JsonResponse en vez de abort() para que AuditApiRequest, que envuelve
 * estas rutas, alcance a registrar también los rechazos.
 */
abstract class AdminController
{
    /**
     * Usuario autenticado con el permiso exigido, o la respuesta que lo niega.
     */
    protected function authorizeAdmin(Request $request, string $permission): User|JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            // Un token técnico no administra nada: la administración es de
            // personas, con nombre y responsabilidad.
            return $this->deny('No autenticado.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasPermission($permission)) {
            return $this->deny('Tu usuario no tiene permiso para realizar esta acción.', Response::HTTP_FORBIDDEN);
        }

        return $user;
    }

    protected function deny(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /** Tamaño de página acotado por la configuración de la API. */
    protected function perPage(Request $request, int $default = 20): int
    {
        return min(max((int) $request->integer('per_page', $default), 1), (int) config('api.max_per_page'));
    }

    /**
     * Añade `success` al sobre del paginador, como el resto de la API.
     *
     * @param  AnonymousResourceCollection  $collection
     */
    protected function paginated($collection): JsonResponse
    {
        $response = $collection->response();
        $payload = $response->getData(true);
        $payload['success'] = true;
        $response->setData($payload);

        return $response;
    }
}
