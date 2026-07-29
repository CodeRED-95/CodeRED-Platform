<?php

namespace App\Services\Integrations;

use App\Enums\ApiTokenType;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class N8nTelegramTokenSettings
{
    private const PREFIX = 'integrations.n8n_telegram.';

    private array $defaults = [
        'enabled' => false,
        'authorized_telegram_user_ids' => [],
        'authorized_telegram_chat_ids' => [],
        'default_expires_in_minutes' => 60,
        'max_expires_in_minutes' => 1440,
        'allowed_abilities' => null,
        'max_pending_per_user' => 1,
        'cooldown_minutes' => 5,
        'approval_timeout_minutes' => 1440,
        'webhook_url' => '',
        'notify_on_approval' => true,
        'notify_on_rejection' => true,
    ];

    public function get(string $key, mixed $fallback = null): mixed
    {
        return Cache::remember('settings:'.self::PREFIX.$key, 300, function () use ($key, $fallback) {
            $row = SystemSetting::query()->where('key', self::PREFIX.$key)->first();
            if (! $row) {
                return $this->defaults[$key] ?? $fallback;
            }

            $value = $row->is_encrypted ? Crypt::decryptString((string) $row->value) : $row->value;

            return json_decode((string) $value, true) ?? $value;
        });
    }

    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            $encrypted = $key === 'shared_secret';
            if ($key === 'shared_secret' && blank($value)) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(['key' => self::PREFIX.$key], ['group' => 'integrations', 'value' => $encrypted ? Crypt::encryptString((string) $value) : json_encode($value, JSON_UNESCAPED_UNICODE), 'is_public' => false, 'is_encrypted' => $encrypted]);
            Cache::forget('settings:'.self::PREFIX.$key);
        }
    }

    public function enabled(): bool
    {
        return (bool) (config('services.n8n.integration_enabled', $this->get('enabled', false)) && config('services.n8n.telegram_token_requests_enabled', true));
    }

    public function sharedSecret(): string
    {
        return (string) (config('services.n8n.shared_secret') ?: $this->get('shared_secret', ''));
    }

    public function webhookUrl(): string
    {
        return (string) (config('services.n8n.webhook_url') ?: $this->get('webhook_url', ''));
    }

    public function allowedAbilities(): array
    {
        $configured = $this->get('allowed_abilities', null);

        $abilities = is_array($configured) && $configured !== []
            ? $configured
            : ApiTokenType::allowedAbilities();

        return array_values(array_unique(array_filter($abilities, fn ($v) => is_string($v) && $v !== '*' && ! Str::startsWith($v, ['admin:', 'users:', 'api-token-requests.']))));
    }

    public function authorizedUsers(): array
    {
        return array_map('strval', (array) $this->get('authorized_telegram_user_ids', []));
    }

    public function authorizedChats(): array
    {
        return array_map('strval', (array) $this->get('authorized_telegram_chat_ids', []));
    }

    public function maskedSecret(): string
    {
        return $this->sharedSecret() === '' ? 'No configurado' : 'Configurado: '.substr(hash('sha256', $this->sharedSecret()), 0, 8).'...';
    }

    public function testConnection(): int
    {
        $url = $this->webhookUrl();
        if ($url === '') {
            return 0;
        }

        return Http::timeout(5)->withHeaders($this->signedHeaders('{}'))->post($url, ['event' => 'codered.connection_test', 'event_uuid' => (string) Str::uuid(), 'occurred_at' => now()->toIso8601String()])->status();
    }

    public function signedHeaders(string $body): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();

        return ['X-CodeRED-Timestamp' => $timestamp, 'X-CodeRED-Nonce' => $nonce, 'X-CodeRED-Signature' => hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, $this->sharedSecret())];
    }
}
