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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IntegrationProtocolService
{
    public const PROTOCOL_VERSION = '1.0';

    public const REQUIRED_CAPABILITIES = ['integration.challenge', 'integration.heartbeat', 'integration.discovery', 'integration.status'];

    public function canonicalPayload(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256', $body);
    }

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

    /** @return array{0: Integration, 1: string} */
    public function claimPairing(string $pairCode, array $payload, ?string $ip = null, ?string $userAgent = null): array
    {
        return DB::transaction(function () use ($pairCode, $payload, $ip, $userAgent): array {
            $pairing = IntegrationPairing::query()->where('provider', 'n8n')->where('pair_code', strtoupper(trim($pairCode)))->lockForUpdate()->first();
            if (! $pairing || $pairing->statusValue() !== IntegrationPairingStatus::Pending->value || $pairing->expiresAt()->isPast()) {
                throw ValidationException::withMessages(['pair_code' => 'El código de pairing no existe, ya fue utilizado o venció.']);
            }
            $secret = bin2hex(random_bytes(32));
            $existingUuid = $pairing->integration_id ? Integration::query()->find($pairing->integration_id)?->integration_uuid : null;
            $integration = Integration::query()->updateOrCreate(['id' => $pairing->integration_id], [
                'integration_uuid' => $existingUuid ?: (string) Str::uuid(),
                'provider' => 'n8n',
                'instance_name' => (string) $payload['instance_name'],
                'instance_url' => $payload['instance_url'] ?? null,
                'environment' => $payload['environment'] ?? null,
                'version' => $payload['n8n_version'] ?? null,
                'n8n_version' => $payload['n8n_version'] ?? null,
                'connector_version' => $payload['connector_version'] ?? null,
                'protocol_version' => $payload['protocol_version'] ?? self::PROTOCOL_VERSION,
                'status' => IntegrationStatus::Pending,
                'encrypted_secret' => Crypt::encryptString($secret),
                'pending_encrypted_secret' => null,
                'pending_secret_expires_at' => null,
                'last_seen_at' => null,
                'connected_at' => null,
                'last_ip' => $ip,
                'created_by' => $pairing->created_by,
            ]);
            $pairing->forceFill(['status' => IntegrationPairingStatus::Claimed, 'integration_id' => $integration->id, 'claimed_at' => now(), 'encrypted_temporary_secret' => Crypt::encryptString('invalidated')])->save();
            $this->log($integration, 'Pairing', 'Instancia n8n conectada por pairing.', ['pair_uuid' => $pairing->pair_uuid], ip: $ip, userAgent: $userAgent);

            return [$integration, $secret];
        });
    }

    public function registerDiscovery(Integration $integration, array $document, ?string $ip = null, ?string $userAgent = null): void
    {
        foreach ((array) ($document['capabilities'] ?? []) as $capability => $definition) {
            if (! is_array($definition)) {
                continue;
            }
            $service = (string) ($definition['service'] ?? $capability);
            $method = strtoupper((string) ($definition['method'] ?? 'POST'));
            $urlPath = parse_url((string) ($definition['url'] ?? ''), PHP_URL_PATH) ?: '';
            $path = (string) ($definition['path'] ?? $urlPath);
            if ($path === '') {
                continue;
            }
            $checksum = hash('sha256', json_encode([$service, $method, $path, $definition['version'] ?? null], JSON_UNESCAPED_SLASHES));
            $existing = IntegrationCapability::query()->where('integration_id', $integration->id)->where('capability', (string) $capability)->first();
            IntegrationCapability::query()->updateOrCreate(['integration_id' => $integration->id, 'capability' => $service], ['service' => $service, 'method' => $method, 'path' => $path, 'version' => $definition['version'] ?? null, 'enabled' => (bool) ($definition['enabled'] ?? true), 'last_seen' => now(), 'checksum' => $checksum]);
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

        $integration->forceFill(['last_seen_at' => now(), 'last_ip' => $ip, 'protocol_version' => $document['protocol_version'] ?? $integration->protocol_version, 'connector_version' => $document['connector_version'] ?? $integration->connector_version, 'n8n_version' => $document['n8n_version'] ?? $integration->n8n_version, 'version' => $document['n8n_version'] ?? $document['version'] ?? $integration->version])->save();
        $this->log($integration, 'Discovery', 'Discovery actualizado.', ['capabilities' => count((array) ($document['capabilities'] ?? [])), 'services' => count((array) ($document['services'] ?? [])), 'plugins' => count((array) ($document['plugins'] ?? []))], ip: $ip, userAgent: $userAgent);
    }

    public function heartbeat(Integration $integration, array $payload, int $latencyMs, ?string $ip = null, ?string $userAgent = null): void
    {
        $integration->forceFill(['status' => IntegrationStatus::Connected, 'last_seen_at' => now(), 'connected_at' => $integration->connected_at ?: now(), 'latency_ms' => $latencyMs, 'last_ip' => $ip, 'protocol_version' => $payload['protocol_version'] ?? $integration->protocol_version, 'connector_version' => $payload['connector_version'] ?? $integration->connector_version, 'n8n_version' => $payload['n8n_version'] ?? $integration->n8n_version, 'version' => $payload['n8n_version'] ?? $payload['version'] ?? $integration->version, 'environment' => $payload['environment'] ?? $integration->environment])->save();
        $this->log($integration, 'Heartbeat', 'Heartbeat recibido.', ['latency_ms' => $latencyMs], ip: $ip, userAgent: $userAgent);
    }

    public function createPendingSecret(Integration $integration): void
    {
        $secret = bin2hex(random_bytes(32));
        $integration->forceFill(['status' => IntegrationStatus::SecretRotationPending, 'pending_encrypted_secret' => Crypt::encryptString($secret), 'pending_secret_expires_at' => now()->addMinutes(10)])->save();
        $this->log($integration, 'Secret Rotation', 'Secreto pendiente generado.');
    }

    public function claimPendingSecret(Integration $integration): string
    {
        if (blank($integration->pending_encrypted_secret) || $integration->pendingSecretExpiresAt()?->isPast()) {
            throw ValidationException::withMessages(['secret' => 'No hay una rotación pendiente vigente.']);
        }

        return Crypt::decryptString($integration->pending_encrypted_secret);
    }

    public function confirmPendingSecret(Integration $integration): void
    {
        if (blank($integration->pending_encrypted_secret)) {
            return;
        }
        $integration->forceFill(['status' => IntegrationStatus::Connected, 'encrypted_secret' => $integration->pending_encrypted_secret, 'pending_encrypted_secret' => null, 'pending_secret_expires_at' => null, 'secret_rotated_at' => now()])->save();
        $this->log($integration, 'Secret Rotation', 'Secreto pendiente confirmado.');
    }

    public function signedHeaders(Integration $integration, string $method, string $path, string $body, ?string $secret = null): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();

        return ['X-CodeRED-Integration' => $integration->integration_uuid, 'X-CodeRED-Timestamp' => $timestamp, 'X-CodeRED-Nonce' => $nonce, 'X-CodeRED-Protocol-Version' => self::PROTOCOL_VERSION, 'X-CodeRED-Signature' => hash_hmac('sha256', $this->canonicalPayload($method, $path, $timestamp, $nonce, $body), $secret ?: $integration->secret())];
    }

    public function challenge(Integration $integration): array
    {
        $challengeId = (string) Str::uuid();
        $challenge = bin2hex(random_bytes(16));
        $capability = $integration->capabilities()->where('service', 'integration.challenge')->where('enabled', true)->first();
        if (! $capability || blank($integration->instance_url)) {
            return ['ok' => false, 'latency_ms' => null, 'message' => 'La instancia no publicó integration.challenge.'];
        }
        $body = json_encode([
            'challenge_id' => $challengeId,
            'challenge' => $challenge,
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
        $started = microtime(true);
        $method = (string) $capability->getAttribute('method');
        $path = (string) $capability->getAttribute('path');
        /** @var Response $response */
        $response = Http::timeout(8)->withHeaders($this->signedHeaders($integration, $method, $path, $body))->withBody($body, 'application/json')->send($method, rtrim((string) $integration->instance_url, '/').$path);
        $latency = (int) round((microtime(true) - $started) * 1000);
        $json = $response->json();
        $valid = is_array($json)
            && ($json['challenge_id'] ?? null) === $challengeId
            && ($json['challenge'] ?? null) === $challenge
            && hash_equals(hash_hmac('sha256', $challenge, $integration->secret()), (string) ($json['signature'] ?? ''));
        $this->log($integration, 'Challenge', $valid ? 'Challenge correcto.' : 'Challenge inválido.', ['latency_ms' => $latency, 'http_status' => $response->status(), 'challenge_id' => $challengeId], level: $valid ? 'info' : 'warning');

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
