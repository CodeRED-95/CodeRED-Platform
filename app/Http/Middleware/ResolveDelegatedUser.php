<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permite que un ApiClient autorizado (ApiClient::canDelegateUsers()) le
 * diga a CodeRED, dentro de una llamada ya autenticada con su propio
 * token técnico, a qué usuario final de CodeRED corresponde la acción —
 * vía el header X-CodeRED-User-Id. El token del cliente sigue siendo la
 * única credencial de autenticación real; este header es solo contexto de
 * atribución, y solo se acepta si el cliente lo tiene explícitamente
 * habilitado.
 *
 * Deliberadamente devuelve Response en vez de abort()/throw: si lanzara
 * una excepción, App\Http\Middleware\AuditApiRequest (que envuelve esta
 * llamada) nunca llegaría a registrar el intento en api_request_logs, y
 * los rechazos de delegación dejarían de ser auditables.
 */
class ResolveDelegatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-CodeRED-User-Id');
        if ($header === null || $header === '') {
            return $next($request);
        }

        $owner = $request->user();
        if (! $owner instanceof ApiClient || ! $owner->canDelegateUsers()) {
            return response()->json(['success' => false, 'message' => 'Este cliente no está autorizado para delegar identidad de usuario.'], 403);
        }

        if (! ctype_digit((string) $header)) {
            return response()->json(['success' => false, 'message' => 'X-CodeRED-User-Id debe ser un identificador numérico.'], 422);
        }

        $user = User::query()->find((int) $header);
        if ($user === null || ! $user->isActive()) {
            return response()->json(['success' => false, 'message' => 'El usuario delegado no existe o no está activo.'], 403);
        }

        // Se atribuye a este usuario en la auditoría aunque el permiso
        // falle a continuación: es una señal de seguridad legítima ("el
        // usuario X intentó consultar sin el permiso requerido"), no un
        // acceso concedido.
        $request->attributes->set('delegated_user', $user);

        $requiredPermission = $owner->delegation_permission;
        if ($requiredPermission !== null && ! $user->hasPermission($requiredPermission)) {
            return response()->json(['success' => false, 'message' => 'El usuario delegado no tiene permiso para esta acción.'], 403);
        }

        return $next($request);
    }
}
