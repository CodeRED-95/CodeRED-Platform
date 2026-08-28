<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Permissions\ChangeUserAccessAction;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;
use App\Services\Permissions\MobileAccess;
use App\Services\Permissions\MobileAccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /**
     * Concede un acceso movil sin esperar a que el usuario lo solicite.
     *
     * Exige el mismo permiso que decidir una solicitud: conceder a mano y
     * aprobar una peticion son la misma facultad, y separarlas dejaria una
     * puerta mas estrecha al lado de una mas ancha.
     */
    public function grantAccess(Request $request, int $id, MobileAccessManager $access): JsonResponse
    {
        return $this->changeAccess($request, $id, $access, conceder: true);
    }

    public function revokeAccess(Request $request, int $id, MobileAccessManager $access): JsonResponse
    {
        return $this->changeAccess($request, $id, $access, conceder: false);
    }

    private function changeAccess(Request $request, int $id, MobileAccessManager $access, bool $conceder): JsonResponse
    {
        $admin = $request->user();

        if (! $admin instanceof User) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        }

        if (! $admin->hasPermission('permission-requests.manage')) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta accion.'], 403);
        }

        // grantable(), no requestable(): lo segundo es lo que el interesado puede
        // pedirse a si mismo desde la app; esto es administracion, y desde aqui
        // se concede tambien el acceso a cada aplicacion. El panel web y Mobile
        // aceptan exactamente el mismo catalogo.
        $validated = $request->validate([
            'permission' => ['required', 'string', Rule::in(MobileAccess::grantable())],
        ]);

        $usuario = User::query()->find($id);

        if ($usuario === null) {
            return response()->json(['success' => false, 'message' => 'El usuario no existe.'], 404);
        }

        $permission = (string) $validated['permission'];

        $resultado = app(ChangeUserAccessAction::class)->execute($usuario, $permission, $conceder, $admin);

        $cambio = $resultado['changed'];
        $etiqueta = $resultado['label'];

        return response()->json([
            'success' => true,
            // Idempotente: si no hubo cambio, tampoco hay error. El estado
            // final es el que se pedia.
            'message' => $cambio
                ? sprintf($conceder ? 'Acceso a %s concedido.' : 'Acceso a %s retirado.', $etiqueta)
                : sprintf($conceder ? 'El usuario ya tenia acceso a %s.' : 'El usuario no tenia acceso a %s.', $etiqueta),
            'data' => ['accesos_moviles' => $access->statusFor($usuario->fresh())],
        ]);
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
