<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usuarios desde CodeRED Mobile: consulta, nada más.
 *
 * Esta primera versión es de sólo lectura a propósito. Crear, editar o cambiar
 * el estado de una persona son acciones con consecuencias que hoy sólo existen
 * en el panel web, con sus confirmaciones y sus salvaguardas (un administrador
 * no puede desactivarse a sí mismo ni quedarse sin rol). Exponerlas por API
 * exigiría replicar esas protecciones, no sólo el endpoint.
 */
class AdminUserController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'users.view');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = User::query()->with('roles')->orderBy('name');

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if (($estado = trim((string) $request->query('estado', ''))) !== '') {
            $query->where('status', $estado);
        }

        return $this->paginated(
            AdminUserResource::collection($query->paginate($this->perPage($request))->withQueryString())
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->authorizeAdmin($request, 'users.view');

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $encontrado = User::query()->with('roles')->find($id);

        if ($encontrado === null) {
            return $this->deny('El usuario no existe.', Response::HTTP_NOT_FOUND);
        }

        return (new AdminUserResource($encontrado))->response();
    }
}
