<?php

namespace App\Http\Middleware;

use App\Services\Integrations\N8nTelegramTokenSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(N8nTelegramTokenSettings::class);
        if (! $settings->enabled() || $settings->sharedSecret() === '') {
            return $this->deny('Integración no disponible.', 403);
        }
        $key = 'n8n-hmac:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return $this->deny('Se superó el límite de solicitudes.', 429);
        }
        RateLimiter::hit($key, 60);
        $timestamp = (string) $request->header('X-CodeRED-Timestamp', '');
        $nonce = (string) $request->header('X-CodeRED-Nonce', '');
        $signature = (string) $request->header('X-CodeRED-Signature', '');
        if (! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300 || $nonce === '' || $signature === '') {
            return $this->invalid($request, 'Encabezados HMAC inválidos.');
        }
        $nonceKey = 'n8n:nonce:'.hash('sha256', $nonce);
        if (! Cache::add($nonceKey, true, 300)) {
            return $this->invalid($request, 'Solicitud repetida.');
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$request->getContent(), $settings->sharedSecret());
        if (! hash_equals($expected, $signature)) {
            return $this->invalid($request, 'Firma inválida.');
        }

        return $next($request);
    }

    private function invalid(Request $request, string $message): Response
    {
        Log::warning('Intento inválido n8n', ['ip' => $request->ip(), 'message' => $message]);

        return $this->deny($message, 401);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
