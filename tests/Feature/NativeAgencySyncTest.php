<?php

namespace Tests\Feature;

use App\Enums\ClientApplication;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Services\Auth\ClientSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NativeAgencySyncTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1/admin/agencies/native-sync';

    protected function setUp(): void
    {
        parent::setUp();
        config(['agency_native_sync.minimum_entries' => 2]);
        Cache::flush();
    }

    private function actor(string $roleSlug = 'admin', bool $manage = true): User
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug]);
        $permissions = ['platform.access', 'agencies.view'];
        if ($manage) {
            $permissions[] = 'agencies.manage_status';
        }
        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function issueSession(User $user, ClientApplication $application = ClientApplication::Platform): string
    {
        return app(ClientSessionManager::class)->start($user, $application, ['device_name' => 'Prueba sincronizador'], Request::create('/'))->accessToken;
    }

    private function bearer(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function fixture(): array
    {
        $origin = Agency::factory()->terrestre()->create(['name' => 'ORIGEN PROTEGIDO', 'old_name' => null, 'status' => AgencyStatus::Active]);
        $present = Agency::factory()->terrestre()->count(12)->create(['status' => AgencyStatus::Active, 'old_name' => null]);
        $inactive = Agency::factory()->terrestre()->create(['status' => AgencyStatus::Inactive, 'old_name' => null]);
        $absent = Agency::factory()->terrestre()->create(['status' => AgencyStatus::Active, 'old_name' => null]);
        $entries = $present->push($inactive)->values()->map(fn ($agency, $i) => [
            'index' => $i, 'label' => implode(', ', [$agency->department, $agency->province, $agency->district]).' - AG. '.$agency->name,
        ])->all();
        $entries[] = ['index' => count($entries), 'label' => 'LIMA, LIMA, LA VICTORIA - AG. SERVICIO LOCAL'];
        $snapshot = ['source_url' => 'https://sysnewos.shalomcontrol.com/service-order/items', 'channel' => 'terrestrial',
            'origin_name' => $origin->name, 'captured_at' => now()->toISOString(), 'filter' => '', 'complete' => true,
            'total' => count($entries), 'entries' => $entries, 'end_observed' => true, 'stable_reads' => 2];

        return compact('origin', 'present', 'inactive', 'absent', 'snapshot');
    }

    public function test_requires_admin_session_not_integration_token_or_viewer(): void
    {
        $this->getJson(self::ROOT.'/status')->assertUnauthorized();
        $admin = $this->actor();
        $this->bearer($admin->createToken('technical', ['*'])->plainTextToken)->getJson(self::ROOT.'/status')->assertForbidden();
        $viewer = $this->actor('viewer');
        $this->bearer($this->issueSession($viewer))->getJson(self::ROOT.'/status')->assertForbidden();
        $this->bearer($this->issueSession($this->actor('limited-admin', false)))->getJson(self::ROOT.'/status')->assertForbidden();
        $this->bearer($this->issueSession($admin))->getJson(self::ROOT.'/status')->assertOk()->assertJsonPath('data.user.id', $admin->id);
    }

    public function test_preview_apply_and_retry_preserve_origin_and_audit_real_changes(): void
    {
        $data = $this->fixture();
        $user = $this->actor();
        $token = $this->issueSession($user);
        $plan = $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()
            ->assertJsonPath('data.activate_count', 1)->assertJsonPath('data.deactivate_count', 1)
            ->assertJsonPath('data.can_apply', true)->assertJsonCount(1, 'data.review')->json('data');
        $this->assertSame(AgencyStatus::Inactive, $data['inactive']->fresh()->status);
        $receipt = $this->bearer($token)->postJson(self::ROOT.'/apply', ['preview_id' => $plan['preview_id']])->assertOk()->json('data');
        $this->assertSame(AgencyStatus::Active, $data['inactive']->fresh()->status);
        $this->assertSame(AgencyStatus::Inactive, $data['absent']->fresh()->status);
        $this->assertSame(AgencyStatus::Active, $data['origin']->fresh()->status);
        $this->assertDatabaseHas('agency_change_logs', ['agency_id' => $data['inactive']->id, 'user_id' => $user->id, 'action' => 'updated']);
        $this->assertDatabaseHas('activity_logs', ['auditable_id' => $data['inactive']->id, 'user_id' => $user->id, 'action' => 'native_sync']);
        $this->bearer($token)->postJson(self::ROOT.'/apply', ['preview_id' => $plan['preview_id']])->assertOk()->assertJsonPath('data', $receipt);
        $this->bearer($token)->postJson(self::ROOT.'/result', ['preview_id' => $plan['preview_id']])->assertOk()->assertJsonPath('data.receipt', $receipt);
        $this->assertDatabaseCount('agencies', 15);
    }

    public function test_rejects_air_filtered_incomplete_old_and_non_sequential_snapshots(): void
    {
        $snapshot = $this->fixture()['snapshot'];
        $token = $this->issueSession($this->actor());
        foreach ([['channel' => 'aerial'], ['filter' => 'LIMA'], ['complete' => false],
            ['captured_at' => now()->subMinutes(11)->toISOString()], ['total' => 999],
            ['entries' => array_slice($snapshot['entries'], 1)]] as $change) {
            $this->bearer($token)->postJson(self::ROOT.'/preview', array_replace($snapshot, $change))->assertUnprocessable();
        }
    }

    public function test_blocks_mass_deactivation_and_unknown_origin(): void
    {
        $data = $this->fixture();
        Agency::factory()->terrestre()->count(8)->create(['status' => AgencyStatus::Active]);
        $token = $this->issueSession($this->actor());
        $plan = $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()->assertJsonPath('data.can_apply', false)->json('data');
        $this->bearer($token)->postJson(self::ROOT.'/apply', ['preview_id' => $plan['preview_id']])->assertUnprocessable();
        $data['snapshot']['origin_name'] = 'ORIGEN DESCONOCIDO';
        $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()->assertJsonPath('data.can_apply', false);
    }

    public function test_stale_expired_and_cross_session_plans_cannot_be_applied(): void
    {
        $data = $this->fixture();
        $admin = $this->actor();
        $token = $this->issueSession($admin);
        $plan = $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()->json('data');
        $otherSession = $this->issueSession($admin);
        $this->bearer($otherSession)->postJson(self::ROOT.'/apply', ['preview_id' => $plan['preview_id']])->assertForbidden();
        $data['absent']->update(['name' => 'CAMBIO CONCURRENTE']);
        $this->bearer($token)->postJson(self::ROOT.'/apply', ['preview_id' => $plan['preview_id']])->assertStatus(409);
        $plan = $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()->json('data');
        $this->travel(11)->minutes();
        $this->bearer($token)->postJson(self::ROOT.'/apply', ['preview_id' => $plan['preview_id']])->assertStatus(409);
    }

    public function test_revoked_permissions_stop_an_existing_plan(): void
    {
        $data = $this->fixture();
        $admin = $this->actor();
        $token = $this->issueSession($admin);
        $plan = $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()->json('data');
        $admin->roles()->first()->permissions()->detach(Permission::query()->where('slug', 'agencies.manage_status')->value('id'));
        $this->bearer($token)->postJson(self::ROOT.'/apply', ['preview_id' => $plan['preview_id']])->assertForbidden();
        $this->assertSame(AgencyStatus::Inactive, $data['inactive']->fresh()->status);
    }

    public function test_ambiguous_duplicates_and_moved_agencies_are_not_automatically_changed(): void
    {
        $data = $this->fixture();
        $candidate = $data['inactive'];
        $duplicate = Agency::factory()->terrestre()->create(['name' => $candidate->name, 'old_name' => null,
            'department' => $candidate->department, 'province' => $candidate->province,
            'district' => $candidate->district, 'status' => AgencyStatus::Active]);
        $moved = $data['present']->first();
        $moved->update(['has_moved' => true]);
        $token = $this->issueSession($this->actor());
        $plan = $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()
            ->assertJsonPath('data.activate_count', 0)->assertJsonPath('data.deactivate_count', 1)->assertJsonCount(3, 'data.review')->json('data');
        $this->assertNotContains($duplicate->id, array_column($plan['changes'], 'id'));
        $data['snapshot']['entries'][] = $data['snapshot']['entries'][0];
        $last = count($data['snapshot']['entries']) - 1;
        $data['snapshot']['entries'][$last]['index'] = $last;
        $data['snapshot']['total']++;
        $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()->assertJsonPath('data.can_apply', false);
    }

    public function test_absent_air_only_or_unclassified_agencies_are_never_deactivated(): void
    {
        $data = $this->fixture();
        $air = Agency::factory()->aereo()->create(['status' => AgencyStatus::Active]);
        $unknown = Agency::factory()->sinIdentificadorExtension()->create(['status' => AgencyStatus::Active]);
        $token = $this->issueSession($this->actor());
        $plan = $this->bearer($token)->postJson(self::ROOT.'/preview', $data['snapshot'])->assertOk()
            ->assertJsonPath('data.deactivate_count', 1)->assertJsonCount(3, 'data.review')->json('data');
        $this->bearer($token)->postJson(self::ROOT.'/apply', ['preview_id' => $plan['preview_id']])->assertOk();
        $this->assertSame(AgencyStatus::Active, $air->fresh()->status);
        $this->assertSame(AgencyStatus::Active, $unknown->fresh()->status);
    }
}
