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
        $data = $request->validate(['pair_code' => ['required', 'string', 'max:20'], 'instance_uuid' => ['required', 'uuid'], 'instance_name' => ['required', 'string', 'max:150'], 'instance_url' => ['required', 'url', 'max:500'], 'environment' => ['required', 'string', 'max:80'], 'n8n_version' => ['nullable', 'string', 'max:80'], 'connector_version' => ['required', 'string', 'max:40'], 'protocol_version' => ['required', 'string', 'max:20']]);
        [$integration, $secret] = $protocol->claimPairing($data['pair_code'], $data, $request->ip(), $request->userAgent(), $request->header('Idempotency-Key'));

        return response()->json(['success' => true, 'data' => ['integration_uuid' => $integration->integration_uuid, 'shared_secret' => $secret, 'protocol_version' => $integration->protocol_version ?: '1.0', 'paired_at' => now()->toIso8601String(), 'discovery_url' => '/api/v1/integrations/n8n/discovery', 'heartbeat_url' => '/api/v1/integrations/n8n/heartbeat', 'challenge_url' => '/api/v1/integrations/n8n/challenge']]);
    }

    public function register(Request $request, IntegrationProtocolService $protocol): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        $data = $request->validate(['protocol_version' => ['nullable', 'string', 'max:20'], 'hostname' => ['nullable', 'string', 'max:255'], 'instance_url' => ['nullable', 'url', 'max:500'], 'environment' => ['nullable', 'string', 'max:80'], 'version' => ['nullable', 'string', 'max:80'], 'connector_version' => ['nullable', 'string', 'max:40'], 'n8n_version' => ['nullable', 'string', 'max:80'], 'workflow_count' => ['nullable', 'integer', 'min:0'], 'workflows_count' => ['nullable', 'integer', 'min:0'], 'credentials' => ['nullable', 'array'], 'capabilities' => ['nullable', 'array'], 'services' => ['nullable', 'array'], 'plugins' => ['nullable', 'array']]);
        $protocol->registerDiscovery($integration, $data, $request->ip(), $request->userAgent());

        return response()->json(['success' => true, 'message' => 'Discovery actualizado.', 'data' => ['integration_uuid' => $integration->integration_uuid, 'capabilities' => $integration->capabilities()->count(), 'services' => $integration->services()->count(), 'plugins' => $integration->plugins()->count()]]);
    }

    public function heartbeat(Request $request, IntegrationProtocolService $protocol): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        $started = microtime(true);
        $data = $request->validate(['instance_uuid' => ['required', 'uuid'], 'timestamp' => ['nullable', 'date'], 'sent_at' => ['nullable', 'date'], 'uptime' => ['nullable', 'integer', 'min:0'], 'latency' => ['nullable', 'integer', 'min:0'], 'workflow_count' => ['nullable', 'integer', 'min:0'], 'active_executions' => ['nullable', 'integer', 'min:0'], 'version' => ['nullable', 'string', 'max:80'], 'n8n_version' => ['nullable', 'string', 'max:80'], 'connector_version' => ['nullable', 'string', 'max:40'], 'protocol_version' => ['nullable', 'string', 'max:20'], 'environment' => ['nullable', 'string', 'max:80']]);
        abort_unless($data['instance_uuid'] === $integration->integration_uuid, 422);
        $latency = (int) round((microtime(true) - $started) * 1000);
        $protocol->heartbeat($integration, $data, $latency, $request->ip(), $request->userAgent());

        return response()->json(['success' => true, 'message' => 'Heartbeat registrado.', 'data' => ['server_time' => now()->toIso8601String(), 'latency_ms' => $latency]]);
    }

    public function rotateSecret(Request $request, IntegrationProtocolService $protocol): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        $secret = $protocol->claimPendingSecret($integration);

        return response()->json(['success' => true, 'message' => 'Secreto pendiente entregado.', 'data' => ['integration_uuid' => $integration->integration_uuid, 'shared_secret' => $secret, 'expires_at' => $integration->pendingSecretExpiresAt()?->toIso8601String()]]);
    }

    public function confirmSecret(Request $request, IntegrationProtocolService $protocol): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        $protocol->confirmPendingSecret($integration);

        return response()->json(['success' => true, 'message' => 'Secreto confirmado.']);
    }

    public function challenge(Request $request, IntegrationProtocolService $protocol): JsonResponse
    {
        /** @var Integration $integration */
        $integration = $request->attributes->get('integration');
        $data = $request->validate(['challenge' => ['required', 'string', 'max:255'], 'sent_at' => ['nullable', 'date']]);
        $protocol->log($integration, 'Challenge', 'Challenge valido recibido desde el agente.', ['stage' => 'CHALLENGE'], ip: $request->ip(), userAgent: $request->userAgent());

        return response()->json(['success' => true, 'data' => ['challenge' => $data['challenge'], 'signature' => hash_hmac('sha256', (string) $data['challenge'], $integration->secret())]]);
    }
}
