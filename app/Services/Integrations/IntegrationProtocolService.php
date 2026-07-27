<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationPairingStatus;
use App\Enums\IntegrationStatus;
use App\Models\Integration;
use App\Models\IntegrationCapability;
use App\Models\IntegrationLog;
use App\Models\IntegrationPairing;
use App\Models\IntegrationPlugin;
use App\Models\IntegrationService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IntegrationProtocolService
{
    public const REQUIRED_CAPABILITIES = ['token.request.created', 'token.request.approved', 'heartbeat'];

    public function createPairing(string $provider = 'n8n', ?int $userId = null, ?Integration $existing = null): IntegrationPairing
    {
        return IntegrationPairing::query()->create([
            'pair_uuid' => (string) Str::uuid(),
            'provider' => $provider,
            'pair_code' => $this->pairCode(),
            'encrypted_temporary_secret' => Crypt::encryptString(Str::random(64)),
            'nonce' => bin2hex(random_bytes(16)),
            'status' => IntegrationPairingStatus::Pending,
            'integration_id' => $existing?->id,
            'expires_at' => now()->addMinutes(10),
            'created_by' => $userId,
        ]);
    }

    public function claimPairing(string $pairCode, array $payload, ?string $ip = null, ?string $userAgent = null): Integration
    {
        $pairing = IntegrationPairing::query()->where('pair_code', strtoupper(trim($pairCode)))->latest()->first();
        if (! $pairing || $pairing->statusValue() !== IntegrationPairingStatus::Pending->value || $pairing->expiresAt()->isPast()) {
            throw ValidationException::withMessages(['pair_code' => 'El código de pairing no existe o ya venció.']);
        }

        $secret = $pairing->temporarySecret();
        $integration = Integration::query()->updateOrCreate(
            ['id' => $pairing->integration_id],
            [
                'integration_uuid' => $pairing->integration_id ? (string) Integration::query()->find($pairing->integration_id)?->integration_uuid : (string) Str::uuid(),
                'provider' => $pairing->provider,
                'instance_name' => (string) $payload['instance_name'],
                'instance_url' => $payload['instance_url'] ?? null,
                'hostname' => $payload['hostname'] ?? null,
                'environment' => $payload['environment'] ?? null,
                'version' => $payload['version'] ?? null,
                'status' => IntegrationStatus::Connected,
                'encrypted_secret' => Crypt::encryptString($secret),
                'last_seen_at' => now(),
                'created_by' => $pairing->created_by,
            ]
        );
        $pairing->forceFill(['status' => IntegrationPairingStatus::Claimed, 'integration_id' => $integration->id, 'claimed_at' => now()])->save();
        $this->log($integration, 'Pairing', 'Instancia conectada por pairing.', ['pair_uuid' => $pairing->pair_uuid], ip: $ip, userAgent: $userAgent);

        return $integration;
    }

    public function registerDiscovery(Integration $integration, array $document, ?string $ip = null, ?string $userAgent = null): void
    {
        foreach ((array) ($document['capabilities'] ?? []) as $capability => $definition) {
            if (! is_array($definition)) {
                continue;
            }
            $service = (string) ($definition['service'] ?? $capability);
            $method = strtoupper((string) ($definition['method'] ?? 'POST'));
            $path = (string) ($definition['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $checksum = hash('sha256', json_encode([$service, $method, $path, $definition['version'] ?? null], JSON_UNESCAPED_SLASHES));
            $existing = IntegrationCapability::query()->where('integration_id', $integration->id)->where('capability', (string) $capability)->first();
            IntegrationCapability::query()->updateOrCreate(['integration_id' => $integration->id, 'capability' => (string) $capability], ['service' => $service, 'method' => $method, 'path' => $path, 'version' => $definition['version'] ?? null, 'enabled' => (bool) ($definition['enabled'] ?? true), 'last_seen' => now(), 'checksum' => $checksum]);
            if ($existing && $existing->checksum !== $checksum) {
                $this->log($integration, 'Webhook Updated', 'Capacidad actualizada automáticamente.', ['capability' => $capability, 'service' => $service]);
            }
        }

        foreach ((array) ($document['services'] ?? []) as $service => $definition) {
            IntegrationService::query()->updateOrCreate(['integration_id' => $integration->id, 'service' => (string) $service], ['enabled' => (bool) data_get($definition, 'enabled', true), 'metadata' => is_array($definition) ? $definition : [], 'last_seen' => now()]);
        }

        foreach ((array) ($document['plugins'] ?? []) as $plugin) {
            if (! is_array($plugin) || blank($plugin['id'] ?? null)) {
                continue;
            }
            IntegrationPlugin::query()->updateOrCreate(['integration_id' => $integration->id, 'plugin_id' => (string) $plugin['id']], ['name' => (string) ($plugin['name'] ?? $plugin['id']), 'version' => $plugin['version'] ?? null, 'enabled' => (bool) ($plugin['enabled'] ?? true), 'metadata' => $plugin, 'last_seen' => now()]);
        }

        $integration->forceFill(['last_seen_at' => now(), 'version' => $document['version'] ?? $integration->version])->save();
        $this->log($integration, 'Discovery', 'Discovery actualizado.', ['capabilities' => count((array) ($document['capabilities'] ?? [])), 'services' => count((array) ($document['services'] ?? [])), 'plugins' => count((array) ($document['plugins'] ?? []))], ip: $ip, userAgent: $userAgent);
    }

    public function heartbeat(Integration $integration, array $payload, int $latencyMs, ?string $ip = null, ?string $userAgent = null): void
    {
        $integration->forceFill(['status' => IntegrationStatus::Connected, 'last_seen_at' => now(), 'latency_ms' => $latencyMs, 'version' => $payload['version'] ?? $integration->version, 'uptime' => $payload['uptime'] ?? null, 'running_workflows' => $payload['running_workflows'] ?? null, 'memory_usage' => $payload['memory_usage'] ?? null, 'cpu_usage' => $payload['cpu_usage'] ?? null, 'hostname' => $payload['hostname'] ?? $integration->hostname, 'environment' => $payload['environment'] ?? $integration->environment])->save();
        $this->log($integration, 'Heartbeat', 'Heartbeat recibido.', ['latency_ms' => $latencyMs], ip: $ip, userAgent: $userAgent);
    }

    public function rotateSecret(Integration $integration): string
    {
        $secret = Str::random(64);
        $integration->forceFill(['encrypted_secret' => Crypt::encryptString($secret), 'secret_rotated_at' => now()])->save();
        $this->log($integration, 'Secret Rotation', 'Secreto regenerado automáticamente.');

        return $secret;
    }

    public function signedHeaders(Integration $integration, string $body): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();

        return ['X-CodeRED-Integration' => $integration->integration_uuid, 'X-CodeRED-Timestamp' => $timestamp, 'X-CodeRED-Nonce' => $nonce, 'X-CodeRED-Signature' => hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, $integration->secret())];
    }

    public function challenge(Integration $integration): array
    {
        $challenge = bin2hex(random_bytes(16));
        $capability = $integration->capabilities()->where('service', 'integration.challenge')->where('enabled', true)->first();
        if (! $capability || blank($integration->instance_url)) {
            return ['ok' => false, 'latency_ms' => null, 'message' => 'La instancia no publicó integration.challenge.'];
        }
        $body = json_encode(['challenge' => $challenge], JSON_UNESCAPED_SLASHES) ?: '{}';
        $started = microtime(true);
        $method = (string) $capability->getAttribute('method');
        $path = (string) $capability->getAttribute('path');
        /** @var Response $response */
        $response = Http::timeout(8)->withHeaders($this->signedHeaders($integration, $body))->withBody($body, 'application/json')->send($method, rtrim((string) $integration->instance_url, '/').$path);
        $latency = (int) round((microtime(true) - $started) * 1000);
        $json = $response->json();
        $valid = is_array($json) && ($json['challenge'] ?? null) === $challenge && hash_equals(hash_hmac('sha256', $challenge, $integration->secret()), (string) ($json['signature'] ?? ''));
        $this->log($integration, 'Challenge', $valid ? 'Challenge correcto.' : 'Challenge inválido.', ['latency_ms' => $latency, 'http_status' => $response->status()], level: $valid ? 'info' : 'warning');

        return ['ok' => $valid, 'latency_ms' => $latency, 'message' => $valid ? 'Correcto' : 'Respuesta inválida'];
    }

    public function log(?Integration $integration, string $event, string $message, array $metadata = [], string $level = 'info', ?int $performedBy = null, ?string $ip = null, ?string $userAgent = null): void
    {
        IntegrationLog::query()->create(['integration_id' => $integration?->id, 'event' => $event, 'level' => $level, 'message' => $message, 'metadata' => $metadata, 'performed_by' => $performedBy, 'ip_address' => $ip, 'user_agent' => $userAgent, 'created_at' => now()]);
    }

    private function pairCode(): string
    {
        do {
            $code = 'CRD-'.strtoupper(Str::random(6));
        } while (IntegrationPairing::query()->where('pair_code', $code)->exists());

        return $code;
    }
}
