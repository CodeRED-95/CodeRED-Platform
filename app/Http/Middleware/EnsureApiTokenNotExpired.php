<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain !== null ? $this->findToken($plain) : null;
        if ($token?->revoked_at !== null) {
            return response()->json(['message' => 'El token fue revocado.'], 401);
        }
        if ($token?->expires_at?->isPast()) {
            return response()->json(['message' => 'El token ha expirado.'], 401);
        }

        return $next($request);
    }

    private function findToken(string $plain): ?ApiToken
    {
        if (! str_contains($plain, '|')) {
            return null;
        }
        [$id, $secret] = explode('|', $plain, 2);
        if (! ctype_digit($id) || $secret === '') {
            return null;
        }
        $token = ApiToken::query()->find((int) $id);

        return $token !== null && hash_equals($token->token, hash('sha256', $secret)) ? $token : null;
    }
}
