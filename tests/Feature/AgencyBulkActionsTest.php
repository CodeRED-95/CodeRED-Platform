<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Index;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Actions\BulkActivateAgenciesAction;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_all_only_selects_current_filtered_page_and_filter_change_clears_selection(): void
    {
        $actor = $this->actor(['agencies.view', 'agencies.manage_status']);
        Agency::factory()->count(18)->create(['status' => AgencyStatus::UnderReview]);
        Agency::factory()->count(3)->create(['status' => AgencyStatus::Active]);

        Livewire::actingAs($actor)->test(Index::class)
            ->set('status', AgencyStatus::UnderReview->value)
            ->call('togglePageSelection')
            ->assertSet('selectedAgencyIds', fn (array $ids): bool => count($ids) === 15)
            ->assertSee('agencias seleccionadas')
            ->set('department', 'Lima')
            ->assertSet('selectedAgencyIds', []);
    }

    public function test_single_selection_clear_and_indeterminate_contract_work(): void
    {
        $agency = Agency::factory()->create();
        Agency::factory()->create();
        $component = Livewire::actingAs($this->actor(['agencies.view', 'agencies.manage_status']))->test(Index::class)
            ->set('selectedAgencyIds', [(string) $agency->id])
            ->assertSee('agencia seleccionada')
            ->assertSeeHtml('.indeterminate = true');

        $component->call('clearSelection')->assertSet('selectedAgencyIds', []);
    }

    public function test_bulk_activation_only_changes_under_review_and_audits_actor(): void
    {
        $actor = $this->actor(['agencies.view', 'agencies.manage_status']);
        $review = Agency::factory()->create(['status' => AgencyStatus::UnderReview]);
        $active = Agency::factory()->create(['status' => AgencyStatus::Active]);
        $inactive = Agency::factory()->create(['status' => AgencyStatus::Inactive]);

        Livewire::actingAs($actor)->test(Index::class)
            ->set('selectedAgencyIds', [$review->id, $active->id, $inactive->id, 999999, $review->id])
            ->assertSee('1 están En revisión')
            ->assertSee('3 serán ignoradas')
            ->call('prepareBulkAction', 'activate')
            ->call('activateSelected')
            ->assertSet('selectedAgencyIds', [])
            ->assertDispatched('toast');

        $this->assertSame(AgencyStatus::Active, $review->fresh()->status);
        $this->assertSame(AgencyStatus::Active, $active->fresh()->status);
        $this->assertSame(AgencyStatus::Inactive, $inactive->fresh()->status);
        $this->assertDatabaseHas('agency_change_logs', ['agency_id' => $review->id, 'user_id' => $actor->id, 'action' => 'updated']);
    }

    public function test_bulk_actions_require_confirmation(): void
    {
        $actor = $this->actor(['agencies.view', 'agencies.manage_status', 'agencies.delete']);
        $agency = Agency::factory()->create(['status' => AgencyStatus::UnderReview]);

        Livewire::actingAs($actor)->test(Index::class)->set('selectedAgencyIds', [$agency->id])
            ->call('activateSelected')->call('deleteSelected');

        $this->assertSame(AgencyStatus::UnderReview, $agency->fresh()->status);
        $this->assertNotSoftDeleted($agency);
    }

    public function test_bulk_delete_uses_soft_delete_and_clears_selection(): void
    {
        $actor = $this->actor(['agencies.view', 'agencies.delete']);
        $first = Agency::factory()->create();
        $second = Agency::factory()->create();

        Livewire::actingAs($actor)->test(Index::class)->set('selectedAgencyIds', [$first->id, $second->id, 999999])
            ->call('prepareBulkAction', 'delete')->call('deleteSelected')
            ->assertSet('selectedAgencyIds', [])->assertDispatched('toast');

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertDatabaseHas('agency_change_logs', ['agency_id' => $first->id, 'user_id' => $actor->id, 'action' => 'deleted']);
    }

    public function test_unauthorized_user_cannot_execute_bulk_actions(): void
    {
        $actor = $this->actor(['agencies.view']);
        $review = Agency::factory()->create(['status' => AgencyStatus::UnderReview]);

        Livewire::actingAs($actor)->test(Index::class)->set('selectedAgencyIds', [$review->id])
            ->call('prepareBulkAction', 'activate')->call('activateSelected')->assertForbidden();
        $this->assertSame(AgencyStatus::UnderReview, $review->fresh()->status);

        Livewire::actingAs($actor)->test(Index::class)->set('selectedAgencyIds', [$review->id])
            ->call('prepareBulkAction', 'delete')->call('deleteSelected')->assertForbidden();
        $this->assertNotSoftDeleted($review);
    }

    public function test_bulk_activation_transaction_rolls_back_all_changes_on_error(): void
    {
        $actor = $this->actor(['agencies.view', 'agencies.manage_status']);
        $first = Agency::factory()->create(['status' => AgencyStatus::UnderReview, 'code' => 'ROLLBACK-1']);
        $second = Agency::factory()->create(['status' => AgencyStatus::UnderReview, 'code' => 'ROLLBACK-2']);
        $this->actingAs($actor);
        Agency::updated(function (Agency $agency): void {
            if ($agency->code === 'ROLLBACK-2') {
                throw new \RuntimeException('Fallo controlado');
            }
        });

        try {
            app(BulkActivateAgenciesAction::class)->execute([$first->id, $second->id]);
            $this->fail('La acción debía revertirse.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo controlado', $exception->getMessage());
        }

        $this->assertSame(AgencyStatus::UnderReview, $first->fresh()->status);
        $this->assertSame(AgencyStatus::UnderReview, $second->fresh()->status);
        $this->assertDatabaseMissing('agency_change_logs', ['agency_id' => $first->id, 'action' => 'updated']);
    }

    public function test_bulk_selection_limit_is_enforced(): void
    {
        $ids = range(1, 101);
        Livewire::actingAs($this->actor(['agencies.view', 'agencies.manage_status']))->test(Index::class)
            ->set('selectedAgencyIds', $ids)->call('prepareBulkAction', 'activate')
            ->assertHasErrors(['selectedAgencyIds']);
    }

    private function actor(array $permissions): User
    {
        $role = Role::query()->create(['slug' => 'role-'.uniqid(), 'name' => 'Rol prueba']);
        $permissionIds = collect($permissions)->map(fn (string $slug): int => Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug])->id)->all();
        $role->permissions()->sync($permissionIds);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
