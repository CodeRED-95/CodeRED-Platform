<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ClientSession;
use App\Models\User;
use App\Services\Auth\AbilityPermissionMap;
use App\Services\Auth\ClientSessionManager;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capa única de autorización de la API.
 *
 * Sustituye a `abilities:` en las rutas funcionales y resuelve los dos
 * mecanismos que conviven en CodeRED sin duplicar rutas ni controladores:
 *
 *   sesión de usuario (kind=session, o sesión web con cookie)
 *       -> se exige el PERMISO RBAC equivalente, consultado en cada petición.
 *          La autoridad es Platform, no el token: retirar un permiso corta el
 *          acceso al instante en Platform, Mobile y Desktop.
 *
 *   token de integración (todo lo demás: n8n, agentes, bridges, tokens
 *   creados desde /admin/api-tokens)
 *       -> se exige la ABILITY declarada en el token, exactamente igual que
 *          antes. Ningún token existente cambia de comportamiento.
 *
 * Se declara con la ability como argumento —`access:dni:consultar`— porque es el
 * nombre público de la capacidad y no cambia según quién llame.
 */
class EnsurePermission
{
    public function __construct(private readonly ClientSessionManager $sessions) {}

    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $authenticatable = $request->user();

        if ($authenticatable === null) {
            return $this->unauthenticated();
        }

        // Los tokens de ApiClient (integraciones sin persona detrás) no tienen
        // RBAC: se resuelven siempre por ability, como hasta ahora.
        if (! $authenticatable instanceof User) {
            return $this->authorizeIntegrationToken($authenticatable, $abilities, $next, $request);
        }

        // Una cuenta desactivada no pasa, tenga el token que tenga.
        if (! $authenticatable->isActive()) {
            return $this->unauthenticated();
        }

        $token = $authenticatable->currentAccessToken();

        return $this->isUserSession($token)
            ? $this->authorizeUserSession($request, $authenticatable, $token, $abilities, $next)
            : $this->authorizeIntegrationToken($authenticatable, $abilities, $next, $request);
    }

    /**
     * Sesión de persona: cookie web (TransientToken) o access token de cliente.
     */
    private function isUserSession(mixed $token): bool
    {
        if ($token instanceof TransientToken) {
            return true;
        }

        return $token instanceof PersonalAccessToken
            && $token->getAttribute('kind') === ClientSessionManager::KIND_SESSION;
    }

    /**
     * @param  list<string>  $abilities
     */
    private function authorizeUserSession(
        Request $request,
        User $user,
        mixed $token,
        array $abilities,
        Closure $next,
    ): Response {
        // La sesión debe seguir viva: revocarla desde Platform corta el acceso
        // sin esperar a que caduque el access token.
        if ($token instanceof PersonalAccessToken) {
            $session = $this->sessions->resolveFromToken($token);

            if (! $session instanceof ClientSession || ! $session->isActive()) {
                return $this->unauthenticated('La sesión se cerró. Inicia sesión de nuevo.');
            }

            if (! $this->sessions->canAccess($user, $session->application)) {
                return $this->forbidden($session->application->accessDeniedMessage());
            }

            $request->attributes->set('client_session', $session);
        }

        foreach ($abilities as $ability) {
            $permission = AbilityPermissionMap::permissionFor($ability);

            // null = la capacidad no exige permiso extra, basta la sesión.
            if ($permission === null) {
                continue;
            }

            if (! $user->hasPermission($permission)) {
                return $this->forbidden();
            }
        }

        return $next($request);
    }

    /**
     * Comportamiento histórico, intacto: la ability tiene que estar declarada en
     * el token. Sirve a n8n, agentes, bridges y a todo token creado desde
     * /admin/api-tokens, sea de un usuario o de un ApiClient.
     *
     * @param  list<string>  $abilities
     */
    private function authorizeIntegrationToken(
        mixed $tokenable,
        array $abilities,
        Closure $next,
        Request $request,
    ): Response {
        foreach ($abilities as $ability) {
            if (! method_exists($tokenable, 'tokenCan') || ! $tokenable->tokenCan($ability)) {
                return $this->forbidden();
            }
        }

        return $next($request);
    }

    private function unauthenticated(string $message = 'No autenticado.'): Response
    {
        return response()->json(['message' => $message], 401);
    }

    private function forbidden(string $message = 'El token no tiene permiso para realizar esta acción.'): Response
    {
        return response()->json(['message' => $message], 403);
    }
}
