<?php

declare(strict_types=1);

namespace Tests\Feature\ShalomRecordar;

use App\Livewire\Admin\ShalomRecordar\Index as ShalomRecordarIndex;
use App\Livewire\Admin\ShalomRecordar\InstallationShow;
use App\Livewire\Admin\ShalomRecordar\UserShow;
use App\Models\Role;
use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class RecordarSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_installation_registration_issues_per_installation_sync_token(): void
    {
        $user = $this->superAdmin();

        $installationUuid = '550e8400-e29b-41d4-a716-446655440000';
        $payload = [
            'email' => $user->email,
            'password' => 'Secret12345!',
            'installation_uuid' => $installationUuid,
            'extension_version' => '2.4.0',
            'installation' => [
                'device_name' => 'Laptop',
                'browser_name' => 'Chrome',
                'browser_version' => '127.0',
                'platform_name' => 'Linux',
                'platform_version' => '6.0',
            ],
        ];

        $response = $this->postJson('/api/v1/shalom-recordar/auth/login', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.installation_uuid', $installationUuid)
            ->assertJsonStructure(['success', 'data' => ['user', 'installation_uuid', 'extension_version', 'sync_token', 'abilities']]);

        $syncToken = (string) $response->json('data.sync_token');
        $this->assertNotSame('', $syncToken);

        $this->assertDatabaseHas('shalom_recordar_installations', [
            'user_id' => $user->id,
            'installation_uuid' => $installationUuid,
            'extension_version' => '2.4.0',
        ]);

        $installation = ShalomRecordarInstallation::query()->where('installation_uuid', $installationUuid)->firstOrFail();
        $this->assertNotNull($installation->sync_token_id);

        Sanctum::actingAs($user, ['shalom-recordar:sync']);
        $this->postJson('/api/v1/shalom-recordar/sync', [
            'installation_uuid' => $installationUuid,
            'extension_version' => '2.4.0',
            'records' => [
                ['record_id' => '1', 'field' => 'DNI', 'value' => '12345678', 'timestamp' => '2026-08-10T10:30:00Z', 'cursor' => 'c1'],
            ],
        ], ['Authorization' => 'Bearer '.$syncToken])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0);

        $this->assertDatabaseHas('shalom_recordar_records', [
            'user_id' => $user->id,
            'installation_uuid' => $installationUuid,
            'field' => 'DNI',
            'value' => '12345678',
        ]);
    }

    public function test_installation_sync_is_idempotent_and_status_is_available(): void
    {
        $user = $this->superAdmin();
        $payload = [
            'email' => $user->email,
            'password' => 'Secret12345!',
            'installation_uuid' => '550e8400-e29b-41d4-a716-446655440010',
            'extension_version' => '2.4.0',
            'installation' => [
                'device_name' => 'Laptop',
                'browser_name' => 'Chrome',
                'browser_version' => '127.0',
                'platform_name' => 'Linux',
                'platform_version' => '6.0',
            ],
        ];

        $register = $this->postJson('/api/v1/shalom-recordar/auth/login', $payload)
            ->assertOk();
        $syncToken = (string) $register->json('data.sync_token');

        $syncPayload = [
            'installation_uuid' => $payload['installation_uuid'],
            'extension_version' => $payload['extension_version'],
            'records' => [
                ['record_id' => '1', 'field' => 'OS', 'value' => 'OS-1', 'timestamp' => '2026-08-10T10:30:00Z', 'cursor' => 'c1'],
            ],
        ];

        Sanctum::actingAs($user, ['shalom-recordar:sync']);
        $this->postJson('/api/v1/shalom-recordar/sync', $syncPayload)
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        Sanctum::actingAs($user, ['shalom-recordar:sync']);
        $this->postJson('/api/v1/shalom-recordar/sync', $syncPayload)
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1);

        Sanctum::actingAs($user, ['shalom-recordar:read-own']);
        $this->getJson('/api/v1/shalom-recordar/sync/status?installation_uuid='.$payload['installation_uuid'].'&extension_version='.$payload['extension_version'], ['Authorization' => 'Bearer '.$syncToken])
            ->assertOk()
            ->assertJsonPath('data.installation_uuid', $payload['installation_uuid']);

        $this->assertSame(1, ShalomRecordarRecord::query()->count());
    }

    public function test_installations_remain_isolated_between_users(): void
    {
        $owner = $this->superAdmin();
        $other = User::factory()->create(['status' => 'active', 'is_active' => true]);
        $other->roles()->attach(Role::query()->where('slug', 'editor')->value('id'));

        $service = app(ShalomRecordarSyncService::class);

        $ownerInstallation = $service->upsertInstallation($owner, [
            'installation_uuid' => '550e8400-e29b-41d4-a716-446655440001',
            'extension_version' => '2.2.0',
        ]);
        $service->syncRecords($owner, $ownerInstallation, [
            ['record_id' => '1', 'field' => 'OS', 'value' => 'OS-1', 'timestamp' => '2026-08-10T10:30:00Z'],
        ]);

        $otherInstallation = $service->upsertInstallation($other, [
            'installation_uuid' => '550e8400-e29b-41d4-a716-446655440002',
            'extension_version' => '2.2.0',
        ]);
        $service->syncRecords($other, $otherInstallation, [
            ['record_id' => '1', 'field' => 'OS', 'value' => 'OS-2', 'timestamp' => '2026-08-10T10:35:00Z'],
        ]);

        $this->assertSame(2, ShalomRecordarInstallation::query()->count());
        $this->assertSame(2, ShalomRecordarRecord::query()->count());
        $this->assertSame(1, ShalomRecordarInstallation::query()->where('user_id', $owner->id)->count());
        $this->assertSame(1, ShalomRecordarInstallation::query()->where('user_id', $other->id)->count());
    }

    public function test_login_emits_limited_token_for_authenticated_user(): void
    {
        $user = $this->superAdmin();

        $response = $this->postJson('/api/v1/shalom-recordar/auth/login', [
            'email' => $user->email,
            'password' => 'Secret12345!',
            'installation_uuid' => '550e8400-e29b-41d4-a716-446655440099',
            'extension_version' => '2.4.0',
        ])->assertOk();

        $this->assertSame(['shalom-recordar:sync', 'shalom-recordar:read-own'], $response->json('data.abilities'));
        $this->assertNotEmpty($response->json('data.sync_token'));
    }

    public function test_admin_screen_loads_with_shalom_recordar_permission(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->get(route('admin.shalom-recordar.index'))
            ->assertOk()
            ->assertSee('Shalom Recordar');

        Livewire::actingAs($user)->test(ShalomRecordarIndex::class)
            ->assertSee('Shalom Recordar');
    }

    public function test_batch_installation_and_user_sync_deletions_are_isolated_and_audited(): void
    {
        $owner = $this->superAdmin();
        $service = app(ShalomRecordarSyncService::class);
        $registered = $service->registerInstallation($owner, [
            'installation_uuid' => '550e8400-e29b-41d4-a716-446655440020',
            'extension_version' => '2.4.0',
        ]);
        $installation = $registered['installation'];

        $service->syncRecords($owner, $installation, [
            ['record_id' => '1', 'field' => 'DNI', 'value' => '12345678', 'timestamp' => '2026-08-10T10:30:00Z', 'batch_id' => 'batch-a'],
            ['record_id' => '2', 'field' => 'OS', 'value' => 'OS-2', 'timestamp' => '2026-08-10T10:35:00Z', 'batch_id' => 'batch-b'],
        ]);

        Livewire::actingAs($owner)->test(InstallationShow::class, ['installation' => $installation])
            ->call('revokeInstallationToken')
            ->assertHasNoErrors();

        $this->assertNotNull($installation->fresh()->syncToken?->revoked_at);

        Livewire::actingAs($owner)->test(InstallationShow::class, ['installation' => $installation])
            ->call('deleteSyncBatch', 'batch-a')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('shalom_recordar_records', [
            'installation_id' => $installation->id,
            'sync_batch_id' => 'batch-a',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'shalom_recordar_sync_batch_deleted']);

        Livewire::actingAs($owner)->test(InstallationShow::class, ['installation' => $installation])
            ->call('deleteInstallationSyncs')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('shalom_recordar_records', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'shalom_recordar_installation_syncs_deleted']);

        Livewire::actingAs($owner)->test(UserShow::class, ['user' => $owner])
            ->call('deleteAllSyncs')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('activity_logs', ['action' => 'shalom_recordar_user_syncs_deleted']);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'password' => bcrypt('Secret12345!')]);
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        return $user;
    }
}
