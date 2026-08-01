<?php

namespace Tests\Feature;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenType;
use App\Livewire\Admin\ApiTokenRequests\Index;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Models\Permission;
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

    public function test_authorized_admin_reveals_delivery_contact_before_delivery_and_audits_without_values(): void
    {
        $admin = $this->superAdmin();
        $request = $this->pendingRequestWithDeliveryContact();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->assertDontSee('cliente@example.test')
            ->assertDontSee('@cliente_demo')
            ->assertDontSee('+51999888777')
            ->call('revealDeliveryContact')
            ->assertSee('cliente@example.test')
            ->assertSee('@cliente_demo')
            ->assertSee('+51999888777');

        $event = ApiTokenRequestEvent::query()->where('api_token_request_id', $request->id)->where('event', 'delivery_contact_viewed')->firstOrFail();
        $metadata = $event->metadata;
        $this->assertIsArray($metadata);
        $this->assertSame(['email', 'telegram', 'whatsapp'], $metadata['fields_viewed']);
        $metadataJson = json_encode($metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('cliente@example.test', $metadataJson);
        $this->assertStringNotContainsString('+51999888777', $metadataJson);
    }

    public function test_admin_without_delivery_contact_permission_cannot_reveal(): void
    {
        $viewer = $this->userWithPermissions(['api-token-requests.view']);
        $request = $this->pendingRequestWithDeliveryContact();

        Livewire::actingAs($viewer)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->assertDontSee('cliente@example.test')
            ->assertSee('c***@example.test')
            ->call('revealDeliveryContact')
            ->assertForbidden();
    }

    public function test_marking_as_delivered_removes_full_contact_and_keeps_masks(): void
    {
        $admin = $this->superAdmin();
        $request = $this->pendingRequestWithDeliveryContact([
            'status' => ApiTokenRequestStatus::Approved,
            'delivery_status' => ApiTokenRequestDeliveryStatus::Pending,
        ]);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->call('confirmDelivery')
            ->assertSee('Esta acción no se puede deshacer')
            ->call('markSelectedAsDelivered')
            ->assertHasNoErrors();

        $request->refresh();
        $this->assertSame(ApiTokenRequestDeliveryStatus::Delivered->value, $request->deliveryStatusValue());
        $this->assertNotNull($request->delivered_at);
        $this->assertSame($admin->id, $request->delivered_by);
        $this->assertNull($request->delivery_email);
        $this->assertNull($request->delivery_telegram_username);
        $this->assertNull($request->delivery_whatsapp_number);
        $this->assertSame('c***@example.test', $request->delivery_email_masked);
        $this->assertSame('@c**********o', $request->delivery_telegram_username_masked);
        $this->assertSame('+51 ******777', $request->delivery_whatsapp_number_masked);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->assertSee('Los datos completos fueron eliminados')
            ->assertDontSee('cliente@example.test')
            ->call('revealDeliveryContact')
            ->assertStatus(410);
    }

    public function test_listing_and_initial_detail_do_not_render_full_delivery_contact(): void
    {
        $admin = $this->superAdmin();
        $request = $this->pendingRequestWithDeliveryContact();

        Livewire::actingAs($admin)->test(Index::class)
            ->assertDontSee('cliente@example.test')
            ->assertDontSee('@cliente_demo')
            ->assertDontSee('+51999888777')
            ->call('selectRequest', $request->id)
            ->assertDontSee('cliente@example.test')
            ->assertDontSee('@cliente_demo')
            ->assertDontSee('+51999888777')
            ->assertSee('c***@example.test')
            ->assertSee('@c**********o')
            ->assertSee('+51 ******777');
    }

    public function test_delivery_contact_masking_and_links_are_normalized(): void
    {
        $this->assertSame('c***@example.test', ApiTokenRequest::maskEmail('cliente@example.test'));
        $this->assertSame('@c**********o', ApiTokenRequest::maskTelegram('@cliente_demo'));
        $this->assertSame('+51 ******777', ApiTokenRequest::maskPhone('+51 999 888 777'));
        $this->assertSame('@cliente_demo', ApiTokenRequest::normalizeTelegram('cliente_demo'));
        $this->assertSame('+51999888777', ApiTokenRequest::normalizePhone('+51 999 888 777'));
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

    private function pendingRequestWithDeliveryContact(array $overrides = []): ApiTokenRequest
    {
        $masked = ApiTokenRequest::maskedContactFromValues('cliente@example.test', '@cliente_demo', '+51 999 888 777');

        return $this->pendingRequest(array_merge([
            'requester_email' => $masked['email'],
            'requester_phone' => $masked['whatsapp'],
            'telegram_username' => $masked['telegram'],
            'delivery_email' => 'cliente@example.test',
            'delivery_telegram_username' => '@cliente_demo',
            'delivery_whatsapp_number' => '+51 999 888 777',
            'delivery_email_masked' => $masked['email'],
            'delivery_telegram_username_masked' => $masked['telegram'],
            'delivery_whatsapp_number_masked' => $masked['whatsapp'],
            'delivery_channel' => 'manual',
            'delivered_to' => $masked['whatsapp'],
        ], $overrides));
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'delivery-viewer'], ['name' => 'Delivery Viewer', 'is_system' => false]);
        foreach ($permissions as $permission) {
            $model = Permission::query()->firstOrCreate(['slug' => $permission], ['name' => $permission]);
            $role->permissions()->syncWithoutDetaching([$model->id]);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
