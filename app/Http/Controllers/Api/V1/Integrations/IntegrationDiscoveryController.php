<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationDiscoveryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['version' => '1.0', 'protocol' => 'codered.integration.discovery', 'required_capabilities' => IntegrationProtocolService::REQUIRED_CAPABILITIES, 'security' => ['hmac' => 'sha256', 'timestamp_tolerance_seconds' => 300, 'nonce' => 'required']]);
    }

    public function pair(Request $request, IntegrationProtocolService $protocol): JsonResponse
    {
        $data = $request->validate(['pair_code' => ['required', 'string', 'max:20'], 'instance_name' => ['required', 'string', 'max:150'], 'instance_url' => ['nullable', 'url', 'max:500'], 'version' => ['nullable', 'string', 'max:80'], 'hostname' => ['nullable', 'string', 'max:120'], 'environment' => ['nullable', 'string', 'max:80']]);
        $integration = $protocol->claimPairing($data['pair_code'], $data, $request->ip(), $request->userAgent());

        return response()->json(['success' => true, 'message' => 'Integración conectada.', 'data' => ['integration_uuid' => $integration->integration_uuid, 'provider' => $integration->provider, 'secret' => $integration->secret(), 'discovery_url' => url('/api/v1/integrations/'.$integration->integration_uuid.'/discovery'), 'heartbeat_url' => url('/api/v1/integrations/'.$integration->integration_uuid.'/heartbeat')]]);
    }

    public function register(Request $request, string $uuid, IntegrationProtocolService $protocol): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        abort_unless($integration->integration_uuid === $uuid, 404);
        $data = $request->validate(['version' => ['nullable', 'string', 'max:40'], 'capabilities' => ['nullable', 'array'], 'services' => ['nullable', 'array'], 'plugins' => ['nullable', 'array']]);
        $protocol->registerDiscovery($integration, $data, $request->ip(), $request->userAgent());

        return response()->json(['success' => true, 'message' => 'Discovery actualizado.', 'data' => ['integration_uuid' => $integration->integration_uuid, 'capabilities' => $integration->capabilities()->count(), 'services' => $integration->services()->count(), 'plugins' => $integration->plugins()->count()]]);
    }

    public function heartbeat(Request $request, string $uuid, IntegrationProtocolService $protocol): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        abort_unless($integration->integration_uuid === $uuid, 404);
        $started = microtime(true);
        $data = $request->validate(['integration_uuid' => ['required', 'uuid'], 'uptime' => ['nullable', 'integer', 'min:0'], 'version' => ['nullable', 'string', 'max:80'], 'running_workflows' => ['nullable', 'integer', 'min:0'], 'memory_usage' => ['nullable', 'integer', 'min:0'], 'cpu_usage' => ['nullable', 'integer', 'min:0'], 'hostname' => ['nullable', 'string', 'max:120'], 'environment' => ['nullable', 'string', 'max:80']]);
        abort_unless($data['integration_uuid'] === $integration->integration_uuid, 422);
        $latency = (int) round((microtime(true) - $started) * 1000);
        $protocol->heartbeat($integration, $data, $latency, $request->ip(), $request->userAgent());

        return response()->json(['success' => true, 'message' => 'Heartbeat registrado.', 'data' => ['server_time' => now()->toIso8601String(), 'latency_ms' => $latency]]);
    }

    public function rotateSecret(Request $request, string $uuid, IntegrationProtocolService $protocol): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        abort_unless($integration->integration_uuid === $uuid, 404);
        $secret = $protocol->rotateSecret($integration);

        return response()->json(['success' => true, 'message' => 'Secreto rotado.', 'data' => ['integration_uuid' => $integration->integration_uuid, 'secret' => $secret, 'rotated_at' => $integration->fresh()?->secretRotatedAt()?->toIso8601String()]]);
    }

    public function challenge(Request $request, string $uuid): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        abort_unless($integration->integration_uuid === $uuid, 404);
        $data = $request->validate(['challenge' => ['required', 'string', 'max:255']]);

        return response()->json(['success' => true, 'data' => ['challenge' => $data['challenge'], 'signature' => hash_hmac('sha256', (string) $data['challenge'], $integration->secret())]]);
    }
}
