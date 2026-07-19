<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        $token = $plainTextToken !== null ? $this->findIncludingExpired($plainTextToken) : null;

        if ($token !== null && $token->expires_at !== null && $token->expires_at->isPast()) {
            return response()->json(['message' => 'El token ha expirado.'], 401);
        }

        return $next($request);
    }

    private function findIncludingExpired(string $plainTextToken): ?PersonalAccessToken
    {
        if (! str_contains($plainTextToken, '|')) {
            return null;
        }

        [$id, $secret] = explode('|', $plainTextToken, 2);
        if (! ctype_digit($id) || $secret === '') {
            return null;
        }

        $token = PersonalAccessToken::query()->find((int) $id);

        return $token !== null && hash_equals($token->token, hash('sha256', $secret)) ? $token : null;
    }
}
