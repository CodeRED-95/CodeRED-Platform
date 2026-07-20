<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuditApiRequest
{
    public function handle(Request $request, Closure $next, string $service): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);
        $owner = $request->user();
        $token = method_exists($owner, 'currentAccessToken') ? $owner->currentAccessToken() : null;
        $dniAudit = json_decode((string) ($request->route('_dni_audit') ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        ApiRequestLog::query()->create([
            'api_client_id' => $owner instanceof ApiClient ? $owner->getKey() : null,
            'token_id' => $token instanceof PersonalAccessToken ? $token->getKey() : null,
            'service' => $service,
            'endpoint' => '/'.$request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
            'identifier_hash' => $service === 'dni' && is_string($request->route('dni')) ? hash('sha256', $request->route('dni')) : null,
            'response_time_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'source' => $dniAudit['source'] ?? null,
            'provider_called' => (bool) ($dniAudit['provider_called'] ?? false),
            'provider_status_code' => $dniAudit['provider_status_code'] ?? null,
            'cache_hit' => (bool) ($dniAudit['cache_hit'] ?? false),
            'local_database_hit' => (bool) ($dniAudit['local_database_hit'] ?? false),
            'created_at' => now(),
        ]);

        return $response;
    }
}
