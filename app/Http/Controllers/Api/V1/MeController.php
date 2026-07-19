<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class MeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $token = $user->currentAccessToken();
        $tokenName = $this->tokenName($token);
        $abilities = $this->tokenAbilities($token);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'token_name' => $tokenName,
            'abilities' => $abilities,
        ]);
    }

    private function tokenName(mixed $token): string
    {
        return $token instanceof PersonalAccessToken ? $token->name : 'Sesión web';
    }

    /** @return list<string> */
    private function tokenAbilities(mixed $token): array
    {
        if ($token instanceof PersonalAccessToken) {
            return array_values(array_filter($token->abilities ?? [], 'is_string'));
        }

        return $token instanceof TransientToken ? ['*'] : [];
    }
}
