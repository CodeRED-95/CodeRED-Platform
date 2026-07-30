<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiTokenType;
use App\Modules\Agencies\Support\AgencyVersion;
use Illuminate\Http\JsonResponse;

class ExtensionChromeConfigController
{
    public function __invoke(): JsonResponse
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $apiBaseUrl = $baseUrl.'/api/'.config('api.version', 'v1');
        $tokenRequestPath = trim((string) config('api.chrome_extension_token_request_path'), '/');

        return response()->json([
            'success' => true,
            'data' => [
                'platform_name' => (string) config('app.name', 'CodeRED Platform'),
                'api_base_url' => $apiBaseUrl,
                'token_request_url' => $baseUrl.'/'.($tokenRequestPath === '' ? 'solicitar-token' : $tokenRequestPath),
                'agency_catalog_version' => (string) AgencyVersion::current(),
                'sync_interval_hours' => (int) config('api.chrome_extension_sync_interval_hours', 24),
                'required_scopes' => ApiTokenType::Agencies->abilities(),
                'endpoints' => [
                    'validate_token' => '/api/v1/me',
                    'catalog_metadata' => '/api/v1/catalog/metadata',
                    'agencies' => '/api/v1/agencies',
                    'changes' => '/api/v1/agencies/changes',
                    'snapshot' => '/api/v1/agencies/snapshot',
                    'version' => '/api/v1/agencies/version',
                ],
            ],
        ])->header('Cache-Control', 'public, max-age=300')->header('X-Content-Type-Options', 'nosniff');
    }
}
