<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\ClientApplication;
use App\Exceptions\InvalidRefreshTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\ClientSession;
use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\AuthAuditor;
use App\Services\Auth\ClientFeatures;
use App\Services\Auth\ClientSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticación de personas en los clientes oficiales de CodeRED.
 *
 * Es el único sitio donde se validan credenciales de usuario para Platform,
 * Mobile y Desktop. Los tokens de API de integración no pasan por aquí: se
 * siguen emitiendo desde la administración de tokens.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly ClientSessionManager $sessions,
        private readonly AuthAuditor $auditor,
        private readonly ClientFeatures $features,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $application = $request->application();
        $user = $this->findUser((string) $request->input('email'));

        // Un solo camino de fallo para credenciales: no se distingue "no existe"
        // de "contraseña incorrecta", y la comprobación del hash se ejecuta
        // siempre para que el tiempo de respuesta no delate qué correos existen.
        $passwordMatches = $user instanceof User
            ? Hash::check((string) $request->input('password'), $user->password)
            : Hash::check((string) $request->input('password'), '$2y$12$'.str_repeat('0', 53));

        if (! $user instanceof User || ! $passwordMatches) {
            $this->auditor->record(AuthAuditor::LOGIN_FAILED, null, $request, null, [
                'application' => $application->value,
                'email' => mb_strtolower(trim((string) $request->input('email'))),
            ]);

            return $this->failure('Credenciales incorrectas.', 422);
        }

        if (! $user->isActive()) {
            $this->auditor->record(AuthAuditor::LOGIN_DENIED, $user, $request, null, [
                'application' => $application->value,
                'reason' => 'inactive',
            ]);

            return $this->failure('Tu cuenta no está activa.', 403);
        }

        // Autorización real de entrada, en backend. Ocultar el botón en el
        // cliente no es seguridad.
        if (! $this->sessions->canAccess($user, $application)) {
            $this->auditor->record(AuthAuditor::LOGIN_DENIED, $user, $request, null, [
                'application' => $application->value,
                'reason' => 'no_app_access',
            ]);

            return $this->failure($application->accessDeniedMessage(), 403);
        }

        $issued = $this->sessions->start($user, $application, $request->device(), $request);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => array_merge($issued->toArray(), $this->identityPayload($user)),
        ]);
    }

    /**
     * Renueva el access token. El refresh se rota: el presentado deja de servir.
     */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string', 'max:255'],
        ]);

        try {
            $issued = $this->sessions->refresh((string) $validated['refresh_token'], $request);
        } catch (InvalidRefreshTokenException $exception) {
            return $this->failure($exception->getMessage(), 401);
        }

        /** @var User $user */
        $user = $issued->session->user;

        return response()->json([
            'success' => true,
            'data' => array_merge($issued->toArray(), $this->identityPayload($user)),
        ]);
    }

    /**
     * Perfil, roles y permisos vigentes. Es lo que Mobile y Desktop usan para
     * construir su interfaz; la autorización real la sigue haciendo el backend
     * en cada endpoint.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $session = $request->attributes->get('client_session');

        if ($session instanceof ClientSession) {
            $session->forceFill(['last_used_at' => now()])->save();
        }

        return response()->json([
            'success' => true,
            'data' => $this->identityPayload($user),
        ]);
    }

    /**
     * Cierra únicamente la sesión con la que se llama. No toca las demás
     * sesiones del usuario ni ninguno de sus tokens de API.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $session = $request->attributes->get('client_session');

        if (! $session instanceof ClientSession) {
            $session = $this->sessions->resolveFromToken($user->currentAccessToken());
        }

        if ($session instanceof ClientSession) {
            $this->sessions->revoke($session, $user, 'logout');
            $this->auditor->record(AuthAuditor::LOGOUT, $user, $request, $session);
        }

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Sesión cerrada correctamente.'],
        ]);
    }

    private function findUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    /**
     * Sólo lo imprescindible para construir la interfaz. Nunca hashes de
     * contraseña, tokens, secretos ni columnas internas.
     *
     * @return array{user:array{id:int,name:string,email:string},roles:list<string>,permissions:list<string>,applications:list<string>,features:array<string,bool>}
     */
    private function identityPayload(User $user): array
    {
        $permissions = $this->permissions($user);

        return [
            'user' => [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'roles' => $user->roles()->pluck('slug')->values()->all(),
            'permissions' => $permissions,
            // Atajo para que el cliente sepa a qué aplicaciones puede entrar sin
            // tener que conocer la convención de nombres de los permisos.
            'applications' => array_values(array_filter(
                ClientApplication::values(),
                static fn (string $app): bool => in_array($app.'.access', $permissions, true),
            )),
            // Qué tiene encendido la INSTALACIÓN, frente a `permissions`, que es
            // lo que puede la PERSONA. Desktop y Mobile necesitan las dos para
            // decidir si pintan un módulo: con el permiso pero la función
            // apagada en el servidor, la llamada respondería 503.
            'features' => $this->features->all(),
        ];
    }

    /** @return list<string> */
    private function permissions(User $user): array
    {
        if ($user->hasRole('super-admin')) {
            return Permission::query()->pluck('slug')->values()->all();
        }

        return $user->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
