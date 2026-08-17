<?php

namespace Tests\Feature;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenType;
use App\Enums\IntegrationStatus;
use App\Livewire\Admin\ApiTokenRequests\Index as TokenRequestsIndex;
use App\Livewire\Admin\ApiTokens\Index as TokensIndex;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\Integration;
use App\Models\Role;
use App\Models\User;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenExpirationDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_defaults_to_thirty_days_and_stores_absolute_expiration(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-29 10:15:00', config('app.timezone')));
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $request = $this->pendingRequest(['requested_expires_in_minutes' => 60]);

        Livewire::actingAs($admin)->test(TokenRequestsIndex::class)
            ->call('selectRequest', $request->id)
            ->assertSet('tokenExpiresInDays', 30)
            ->set('approvalUserId', $owner->id)
            ->set('approvalTokenTypes', [ApiTokenType::Dni->value])
            ->call('approve')
            ->assertHasNoErrors();

        $token = ApiToken::query()->sole();
        $this->assertSame('2026-08-28 10:15:00', $token->expires_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('api_token_requests', [
            'id' => $request->id,
            'token_expires_in_days' => 30,
        ]);
    }

    public function test_approval_accepts_quick_day_values_for_all_token_types(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-29 09:00:00', config('app.timezone')));
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $daysByType = [
            ApiTokenType::Dni->value => 1,
            ApiTokenType::Ruc->value => 7,
            ApiTokenType::Agencies->value => 365,
        ];

        foreach ($daysByType as $type => $days) {
            $request = $this->pendingRequest();
            Livewire::actingAs($admin)->test(TokenRequestsIndex::class)
                ->call('selectRequest', $request->id)
                ->set('approvalUserId', $owner->id)
                ->set('approvalTokenTypes', [$type])
                ->set('tokenExpiresInDays', $days)
                ->call('approve')
                ->assertHasNoErrors();

            $token = ApiToken::query()->latest('id')->firstOrFail();
            $this->assertSame(now()->addDays($days)->toIso8601String(), $token->expires_at?->toIso8601String());
        }
    }

    public function test_approval_rejects_invalid_day_values_without_1440_minute_error(): void
    {
        $admin = $this->superAdmin();
        $owner = User::factory()->create();

        foreach ([0, -1, 1.5, 366] as $value) {
            $request = $this->pendingRequest();
            Livewire::actingAs($admin)->test(TokenRequestsIndex::class)
                ->call('selectRequest', $request->id)
                ->set('approvalUserId', $owner->id)
                ->set('approvalTokenTypes', [ApiTokenType::Agencies->value])
                ->set('tokenExpiresInDays', $value)
                ->call('approve')
                ->assertHasErrors(['tokenExpiresInDays'])
                ->assertDontSee('1440');
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_manual_generation_uses_same_day_expiration_for_all_types(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-29 12:00:00', config('app.timezone')));
        $admin = $this->superAdmin();
        $owner = User::factory()->create();

        foreach ([1, 7, 30, 90, 180, 365] as $days) {
            Livewire::actingAs($admin)->test(TokensIndex::class)
                ->set('name', 'Token '.$days)
                ->set('targetUserId', $owner->id)
                ->set('abilities', ApiTokenType::Ruc->abilities())
                ->set('tokenExpiresInDays', $days)
                ->call('createToken')
                ->assertHasNoErrors();

            $token = ApiToken::query()->latest('id')->firstOrFail();
            $this->assertSame(now()->addDays($days)->toIso8601String(), $token->expires_at?->toIso8601String());
        }
    }

    public function test_n8n_contract_prefers_days_and_keeps_legacy_minutes_compatible(): void
    {
        config(['services.n8n.integration_enabled' => true]);
        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests', [
            'requester_name' => 'Ada',
            'application_name' => 'Bot Days',
            'purpose' => 'Test',
            'requested_scopes' => ['agencies:read'],
            'requested_token_expires_in_days' => 90,
            'expires_in_minutes' => 60,
        ])->assertCreated()->assertJsonPath('data.requested_token_expires_in_days', 90);

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests', [
            'requester_name' => 'Grace',
            'application_name' => 'Bot Minutes',
            'purpose' => 'Test',
            'requested_scopes' => ['agencies:read'],
            'expires_in_minutes' => 1500,
        ])->assertCreated()->assertJsonPath('data.requested_token_expires_in_days', 2);
    }

    private function pendingRequest(array $overrides = []): ApiTokenRequest
    {
        return ApiTokenRequest::query()->create(array_merge([
            'request_uuid' => (string) Str::uuid(),
            'requester_name' => 'Ada Lovelace',
            'application_name' => 'Operations Bot',
            'telegram_user_id' => 'n8n:test-user:'.Str::uuid(),
            'telegram_chat_id' => 'n8n:test-chat',
            'requested_token_name' => 'Operations Bot',
            'requested_token_type' => 'agencies',
            'requested_token_expires_in_days' => null,
            'requested_abilities' => ['agencies:read'],
            'requested_expires_in_minutes' => 60,
            'status' => ApiTokenRequestStatus::Pending,
            'request_source' => 'n8n',
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
        ], $overrides));
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function signedJson(string $method, string $path, array $payload = [])
    {
        $secret = 'test-shared-secret';
        $integration = Integration::query()->create([
            'integration_uuid' => (string) Str::uuid(),
            'instance_uuid' => (string) Str::uuid(),
            'provider' => 'n8n',
            'instance_name' => 'n8n Test',
            'instance_url' => 'https://n8n.test',
            'environment' => 'testing',
            'protocol_version' => '1.0',
            'status' => IntegrationStatus::Connected,
            'encrypted_secret' => Crypt::encryptString($secret),
        ]);
        $body = $method === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $canonical = app(IntegrationProtocolService::class)->canonicalPayload($method, $path, $timestamp, $nonce, $body ?: '');

        return $this->withHeaders([
            'X-CodeRED-Integration' => $integration->integration_uuid,
            'X-CodeRED-Timestamp' => $timestamp,
            'X-CodeRED-Nonce' => $nonce,
            'X-CodeRED-Signature' => hash_hmac('sha256', $canonical, $secret),
            'X-CodeRED-Protocol-Version' => '1.0',
            'Accept' => 'application/json',
        ])->json($method, $path, $payload);
    }
}
