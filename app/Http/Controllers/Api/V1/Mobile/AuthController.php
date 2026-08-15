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

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userPayload($user),
                'roles' => $this->rolesPayload($user),
                'permissions' => $this->permissionsPayload($user),
            ],
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
