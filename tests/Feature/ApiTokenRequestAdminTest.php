<?php

namespace Tests\Feature;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenType;
use App\Livewire\Admin\ApiTokenRequests\Index;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_details_open_and_render_audit_history_without_entry_error(): void
    {
        $admin = $this->superAdmin();
        $request = $this->pendingRequest(['requested_token_type' => null]);
        $request->events()->create([
            'event' => 'created',
            'description' => 'Solicitud creada desde prueba.',
            'metadata' => ['requested_token_type' => null, 'secret' => 'hidden'],
            'created_at' => now(),
        ]);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->assertSee('Detalle de solicitud')
            ->assertSee('Historial de eventos')
            ->assertSee('Solicitud creada desde prueba.')
            ->assertDontSee('>hidden<', false);
    }

    public function test_approval_requires_token_type(): void
    {
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $request = $this->pendingRequest();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->set('approvalTokenName', 'Token sin tipo')
            ->set('approvalUserId', $owner->id)
            ->set('approvalTokenType', '')
            ->call('approve')
            ->assertHasErrors(['approvalTokenType']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_approval_maps_each_token_type_to_canonical_abilities(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        $owner = User::factory()->create();

        foreach (ApiTokenType::cases() as $type) {
            $request = $this->pendingRequest(['requested_token_type' => $type === ApiTokenType::Ruc ? 'dni' : $type->value]);

            Livewire::actingAs($admin)->test(Index::class)
                ->call('selectRequest', $request->id)
                ->set('approvalTokenName', $type->label())
                ->set('approvalUserId', $owner->id)
                ->set('approvalTokenType', $type->value)
                ->call('approve')
                ->assertHasNoErrors();

            $request->refresh();
            $token = ApiToken::query()->findOrFail($request->personal_access_token_id);
            $this->assertSame(ApiTokenRequestStatus::Approved->value, $request->statusValue());
            $this->assertSame($type->value, $request->token_type);
            $this->assertSame($type->abilities(), $request->requested_abilities);
            $this->assertSame($type->abilities(), $token->abilities);
            $this->assertNotNull($request->encrypted_plain_text_token);
            $this->assertStringNotContainsString($request->encrypted_plain_text_token, $request->events()->pluck('metadata')->toJson());
        }
    }

    public function test_double_approval_does_not_create_a_second_token(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $request = $this->pendingRequest();

        $component = Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->set('approvalTokenName', 'Token DNI')
            ->set('approvalUserId', $owner->id)
            ->set('approvalTokenType', 'dni')
            ->set('tokenExpiresInDays', 30)
            ->call('approve')
            ->assertHasNoErrors();

        $component->call('approve')->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_pending_request_can_be_rejected_without_reason(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        $request = $this->pendingRequest();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->set('rejectionReason', '')
            ->call('reject')
            ->assertHasNoErrors();

        $request->refresh();
        $this->assertSame(ApiTokenRequestStatus::Rejected->value, $request->statusValue());
        $this->assertNull($request->rejection_reason);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function pendingRequest(array $overrides = []): ApiTokenRequest
    {
        return ApiTokenRequest::query()->create(array_merge([
            'request_uuid' => (string) Str::uuid(),
            'requester_name' => 'Ada Lovelace',
            'requester_email' => 'ada@example.test',
            'application_name' => 'Operations Bot',
            'purpose' => 'Consultar datos desde n8n',
            'telegram_user_id' => 'n8n:test-user:'.Str::uuid(),
            'telegram_chat_id' => 'n8n:test-chat',
            'requested_token_name' => 'Operations Bot',
            'requested_token_type' => 'agencies',
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
}
