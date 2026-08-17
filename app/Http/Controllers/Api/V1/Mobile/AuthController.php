<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Mobile\LoginRequest;
use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\MobileTokenAbilityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Throwable;

class AuthController extends Controller
{
    public function login(LoginRequest $request, MobileTokenAbilityResolver $abilityResolver): JsonResponse
    {
        $user = $this->findUser((string) $request->input('email'));

        if (! $user instanceof User || ! Hash::check((string) $request->input('password'), $user->password) || ! $user->isActive()) {
            Log::warning('mobile_login_failed', [
                'email' => mb_strtolower(trim((string) $request->input('email'))),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
            ], 422);
        }

        $deviceName = trim((string) $request->input('device_name', ''));
        $tokenName = $deviceName !== '' ? 'codered-mobile - '.$deviceName : 'codered-mobile';
        $abilities = $abilityResolver->resolve($user);
        $tokenResult = $user->createToken($tokenName, $abilities);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Log::info('mobile_login_success', [
            'user_id' => $user->id,
            'token_id' => $tokenResult->accessToken->getKey(),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userPayload($user),
                'roles' => $this->rolesPayload($user),
                'permissions' => $this->permissionsPayload($user),
                'token' => $tokenResult->plainTextToken,
            ],
        ]);
    }

    public function me(Request $request, MobileTokenAbilityResolver $abilityResolver): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $this->syncTokenAbilities($user, $abilityResolver);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userPayload($user),
                'roles' => $this->rolesPayload($user),
                'permissions' => $this->permissionsPayload($user),
            ],
        ]);
    }

    /**
     * Recalcula las abilities del token actual desde el RBAC vigente.
     *
     * Las abilities se fijan al iniciar sesion, asi que un permiso concedido
     * despues no llegaba al token: la persona tenia el permiso pero su token
     * seguia sin la ability, y la unica salida era cerrar sesion y volver a
     * entrar. Como la app ya llama a /me para refrescar sus permisos, aqui es
     * donde el token se pone al dia.
     *
     * Esto no debilita Sanctum. Las abilities no se amplian arbitrariamente: se
     * **recalculan** con el mismo resolver que las emitio, a partir de los
     * permisos reales de la persona en ese instante. Y funciona en las dos
     * direcciones -si a alguien le retiran un permiso, la ability desaparece en
     * la siguiente llamada-, cosa que antes no ocurria hasta el logout. Es, por
     * tanto, mas estricto que lo que habia.
     */
    private function syncTokenAbilities(User $user, MobileTokenAbilityResolver $abilityResolver): void
    {
        $token = $user->currentAccessToken();

        // Debe ser un token realmente guardado. Una sesion por cookie usa
        // TransientToken, y en las pruebas Sanctum::actingAs inyecta un doble
        // que supera el instanceof pero no se puede persistir: en ambos casos
        // no hay nada que sincronizar.
        if (! $token instanceof PersonalAccessToken || ! $token->exists || $token->getKey() === null) {
            return;
        }

        $actuales = array_values((array) ($token->abilities ?? []));
        $esperadas = $abilityResolver->resolve($user);

        sort($actuales);
        sort($esperadas);

        if ($actuales === $esperadas) {
            return;
        }

        try {
            $token->forceFill(['abilities' => $esperadas])->save();
        } catch (Throwable $exception) {
            // Poner el token al dia es una mejora, no el proposito de /me:
            // quien pide su perfil debe recibirlo aunque esto falle.
            Log::warning('mobile_token_abilities_sync_failed', [
                'user_id' => $user->getKey(),
                'reason' => $exception->getMessage(),
            ]);

            return;
        }

        Log::info('mobile_token_abilities_synced', [
            'user_id' => $user->getKey(),
            'token_id' => $token->getKey(),
            'abilities' => count($esperadas),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $currentToken = $user->currentAccessToken();
        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        } elseif ($currentToken instanceof TransientToken) {
            // Sesión web no debe verse afectada por el logout móvil.
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            $token = PersonalAccessToken::findToken($bearer);
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Sesión móvil cerrada correctamente.',
            ],
        ]);
    }

    private function findUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->with('roles.permissions')
            ->first();
    }

    /** @return array{id:int,name:string,email:string} */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /** @return list<string> */
    private function rolesPayload(User $user): array
    {
        return $user->roles()->pluck('slug')->values()->all();
    }

    /** @return list<string> */
    private function permissionsPayload(User $user): array
    {
        if ($user->hasRole('super-admin')) {
            return Permission::query()->pluck('slug')->values()->all();
        }

        return $user->roles()->with('permissions')->get()->flatMap(fn ($role) => $role->permissions->pluck('slug'))->unique()->values()->all();
    }
}
