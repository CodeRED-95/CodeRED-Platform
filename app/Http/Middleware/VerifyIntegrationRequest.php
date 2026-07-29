<?php

namespace App\Http\Middleware;

use App\Models\Integration;
use App\Services\Integrations\IntegrationProtocolService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyIntegrationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = (string) $request->header('X-CodeRED-Integration', $request->input('integration_uuid', ''));
        if (! Str::isUuid($uuid)) {
            return $this->deny('Integración no reconocida.', 401);
        }

        $integration = Integration::query()->where('integration_uuid', $uuid)->first();
        if (! $integration) {
            return $this->deny('Integración no reconocida.', 401);
        }
        if ($integration->isRevoked()) {
            return $this->deny('Integración revocada.', 403);
        }

        $allowlist = (array) ($integration->ip_allowlist ?? []);
        if ($allowlist !== [] && ! in_array($request->ip(), $allowlist, true)) {
            return $this->deny('IP no autorizada.', 403);
        }

        $key = 'integration-hmac:'.$integration->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 120)) {
            return $this->deny('Se superó el límite de solicitudes.', 429);
        }
        RateLimiter::hit($key, 60);

        $timestamp = (string) $request->header('X-CodeRED-Timestamp', '');
        $nonce = (string) $request->header('X-CodeRED-Nonce', '');
        $signature = (string) $request->header('X-CodeRED-Signature', '');
        if (! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300 || $nonce === '' || $signature === '') {
            return $this->deny('Encabezados HMAC inválidos.', 401);
        }
        if (! Cache::add('integration:nonce:'.hash('sha256', $integration->id.':'.$nonce), true, 300)) {
            return $this->deny('Solicitud repetida.', 409);
        }

        $path = '/'.ltrim($request->getPathInfo(), '/');
        $body = $request->getContent();
        $canonical = app(IntegrationProtocolService::class)->canonicalPayload($request->getMethod(), $path, $timestamp, $nonce, $body);
        $expected = hash_hmac('sha256', $canonical, $integration->secret());
        if (! hash_equals($expected, $signature)) {
            return $this->deny('Firma inválida.', 401);
        }

        $request->attributes->set('integration', $integration);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
