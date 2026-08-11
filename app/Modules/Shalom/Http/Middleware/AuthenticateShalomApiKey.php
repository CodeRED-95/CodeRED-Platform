<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Http\Middleware;

use App\Modules\Shalom\Models\ShalomApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateShalomApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key required. Use header: X-Shalom-API-Key',
            ], 401);
        }

        $keyRecord = $this->verifyApiKey($apiKey);

        if (! $keyRecord) {
            \Log::warning('Invalid Shalom API key attempt', [
                'ip' => $request->ip(),
                'credential_source' => $this->credentialSource($request),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or revoked API key',
            ], 401);
        }

        // Registrar uso
        $keyRecord->recordUsage();

        // Guardar referencia para uso posterior
        $request->attributes->set('shalom_api_key', $keyRecord);
        $request->attributes->set('shalom_user_id', $keyRecord->user_id);
        $request->attributes->set('shalom_username', $keyRecord->name);

        return $next($request);
    }

    /**
     * Extrae la API key del header o query parameter
     */
    private function extractApiKey(Request $request): ?string
    {
        // Opción 1: Header X-Shalom-API-Key (recomendado)
        if ($request->hasHeader('X-Shalom-API-Key')) {
            return $request->header('X-Shalom-API-Key');
        }

        // Opción 2: Bearer token (formato: Bearer shalom_xxx)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Opción 3: Query parameter (menos seguro, para desarrollo)
        if ($request->has('api_key')) {
            return $request->query('api_key');
        }

        return null;
    }

    /**
     * Verifica si la API key es válida
     */
    private function verifyApiKey(string $plainKey): ?ShalomApiKey
    {
        $keyPrefix = substr($plainKey, 0, 20);

        $keyRecord = ShalomApiKey::where('key_prefix', $keyPrefix)
            ->active()
            ->first();

        if (! $keyRecord) {
            return null;
        }

        if (! $keyRecord->verifyKey($plainKey)) {
            return null;
        }

        return $keyRecord;
    }

    private function credentialSource(Request $request): string
    {
        if ($request->hasHeader('X-Shalom-API-Key')) {
            return 'x-shalom-api-key';
        }

        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return 'authorization-bearer';
        }

        if ($request->has('api_key')) {
            return 'query-api_key';
        }

        return 'unknown';
    }
}
