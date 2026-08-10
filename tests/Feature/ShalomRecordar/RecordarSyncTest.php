<?php

declare(strict_types=1);

namespace Tests\Feature\ShalomRecordar;

use App\Livewire\Admin\ShalomRecordar\Index as ShalomRecordarIndex;
use App\Models\Role;
use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Laravel\Sanctum\Sanctum;
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
        $bootstrap = $user->createToken('Shalom Recordar Bootstrap', ['shalom-recordar:bootstrap'])->plainTextToken;

        $installationUuid = '550e8400-e29b-41d4-a716-446655440000';
        $payload = [
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

        $response = $this->postJson('/api/v1/shalom-recordar/installations/register', $payload, ['Authorization' => 'Bearer '.$bootstrap])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.installation_uuid', $installationUuid)
            ->assertJsonStructure(['success', 'data' => ['installation_uuid', 'extension_version', 'sync_token']]);

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
        $bootstrap = $user->createToken('Shalom Recordar Bootstrap', ['shalom-recordar:bootstrap'])->plainTextToken;
        $payload = [
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

        $register = $this->postJson('/api/v1/shalom-recordar/installations/register', $payload, ['Authorization' => 'Bearer '.$bootstrap])
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

        Sanctum::actingAs($user, ['shalom-recordar:sync']);
        $this->getJson('/api/v1/shalom-recordar/sync/status?installation_uuid='.$payload['installation_uuid'].'&extension_version='.$payload['extension_version'])
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

    public function test_admin_screen_loads_with_shalom_recordar_permission(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->get(route('admin.shalom-recordar.index'))
            ->assertOk()
            ->assertSee('Shalom Recordar');

        Livewire::actingAs($user)->test(ShalomRecordarIndex::class)
            ->assertSee('Shalom Recordar');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'password' => bcrypt('Secret12345!')]);
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        return $user;
    }
}
