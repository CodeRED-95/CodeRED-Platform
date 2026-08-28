<?php

namespace App\Http\Middleware;

use App\Core\Api\Enums\ApiRequestType;
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
        $dniNameAudit = json_decode((string) ($request->route('_dni_name_audit') ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        $rucAudit = json_decode((string) ($request->route('_ruc_audit') ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        $serviceAudit = match ($service) {
            'ruc' => $rucAudit,
            'dni-name-search' => $dniNameAudit,
            default => $dniAudit,
        };
        $delegatedUser = $request->attributes->get('delegated_user');
        // Id de correlación opcional (p. ej. X-Request-Id de Declaración
        // Jurada): permite detectar reintentos del mismo request lógico —
        // ver ResolveDelegatedUser y el bridge DNI en
        // packages/shalom-declaracion-jurada/server/app-backend.js.
        $requestId = mb_substr((string) $request->header('X-Request-Id', ''), 0, 64) ?: null;
        $isDuplicate = $requestId !== null && ApiRequestLog::query()->where('request_id', $requestId)->exists();
        ApiRequestLog::query()->create([
            'api_client_id' => $owner instanceof ApiClient ? $owner->getKey() : null,
            'token_id' => $token instanceof PersonalAccessToken ? $token->getKey() : null,
            'delegated_user_id' => $delegatedUser?->getKey(),
            'request_id' => $requestId,
            'is_duplicate_request' => $isDuplicate,
            'request_type' => (string) $request->attributes->get('request_type', ApiRequestType::Api->value),
            'service' => $service,
            'endpoint' => '/'.$request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
            'identifier_hash' => $this->identifierHash($request, $service),
            'response_time_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'source' => $serviceAudit['source'] ?? null,
            'provider_called' => (bool) ($serviceAudit['provider_called'] ?? false),
            'provider_status_code' => $serviceAudit['provider_status_code'] ?? null,
            'cache_hit' => (bool) ($serviceAudit['cache_hit'] ?? false),
            'local_database_hit' => (bool) ($serviceAudit['local_database_hit'] ?? false),
            'created_at' => now(),
        ]);

        return $response;
    }

    private function identifierHash(Request $request, string $service): ?string
    {
        if (in_array($service, ['dni', 'ruc'], true) && is_string($request->route($service))) {
            return hash('sha256', $request->route($service));
        }

        if ($service === 'dni-name-search') {
            $values = array_map(static fn (mixed $value): string => mb_strtoupper(trim((string) $value)), [
                $request->input('nombres'),
                $request->input('apellido_paterno'),
                $request->input('apellido_materno'),
            ]);
            if (implode('', $values) !== '') {
                return hash('sha256', implode('|', $values));
            }
        }

        return null;
    }
}
