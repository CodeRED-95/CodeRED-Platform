<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate([
            'slug' => 'super-admin',
        ], [
            'name' => 'Super Administrador',
            'is_system' => true,
        ]);
        $actor = User::factory()->create();
        $actor->roles()->attach($role);

        return $actor;
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_super_admin_sees_complete_and_real_dashboard_metrics(): void
    {
        $initialUsers = User::query()->count();
        $initialNewUsers = User::query()->where('created_at', '>=', now()->subDays(30))->count();
        $initialAgencies = Agency::query()->count();
        $initialByStatus = collect(AgencyStatus::cases())->mapWithKeys(
            fn (AgencyStatus $status): array => [
                $status->value => Agency::query()->where('status', $status)->count(),
            ],
        );

        $actor = $this->superAdmin();
        User::factory()->create();
        User::factory()->create(['created_at' => now()->subDays(40)]);

        foreach (AgencyStatus::cases() as $status) {
            Agency::factory()->create([
                'status' => $status,
                'has_moved' => $status === AgencyStatus::Moved,
                'moved_to_address' => $status === AgencyStatus::Moved ? 'Nueva ubicación' : null,
            ]);
        }

        Livewire::actingAs($actor)
            ->test(Dashboard::class)
            ->assertSet('period', 30)
            ->assertViewHas('agencyMetrics', fn (array $metrics): bool => $metrics === [
                'total' => $initialAgencies + 5,
                'active' => $initialByStatus[AgencyStatus::Active->value] + 1,
                'inactive' => $initialByStatus[AgencyStatus::Inactive->value] + 1,
                'temporarily_closed' => $initialByStatus[AgencyStatus::TemporarilyClosed->value] + 1,
                'under_review' => $initialByStatus[AgencyStatus::UnderReview->value] + 1,
                'moved' => $initialByStatus[AgencyStatus::Moved->value] + 1,
            ])
            ->assertViewHas('userMetrics', fn (array $metrics): bool => $metrics === [
                'total' => $initialUsers + 3,
                'new' => $initialNewUsers + 2,
            ])
            ->assertViewHas('statusDistribution', fn (array $distribution): bool => count($distribution) === 5)
            ->assertViewHas('agencyTrend', fn (array $trend): bool => count($trend) === 30)
            ->assertSee('Total de usuarios')
            ->assertSee('Tendencia de agencias')
            ->assertSee('Distribución por estado')
            ->assertSee('Resumen secundario')
            ->assertSeeHtml('id="dashboard-trend-area"')
            ->assertSeeHtml('fill="none"')
            ->assertSee('Trasladadas');
    }

    public function test_dashboard_period_updates_real_user_and_agency_series(): void
    {
        $actor = $this->superAdmin();
        User::factory()->create(['created_at' => now()->subDays(8)]);
        User::factory()->create(['created_at' => now()->subDays(2)]);
        Agency::factory()->create(['created_at' => now()->subDays(6)]);

        $component = Livewire::actingAs($actor)->test(Dashboard::class);

        foreach ([7, 30, 90] as $period) {
            $component
                ->set('period', $period)
                ->assertViewHas('agencyTrend', fn (array $trend): bool => count($trend) === $period)
                ->assertSee('Últimos '.$period.' días');
        }

        $component
            ->set('period', 7)
            ->assertViewHas('agencyTrend', fn (array $trend): bool => collect($trend)->sum('count') >= 1)
            ->assertViewHas('userMetrics', fn (array $metrics): bool => $metrics['new'] >= 2);
    }

    /**
     * El importador manual fue retirado: el panel resume ahora la última
     * ejecución de la sincronización Shalom, que sigue en uso.
     */
    public function test_dashboard_shows_real_activity_and_last_shalom_sync_run(): void
    {
        $actor = $this->superAdmin();
        $agency = Agency::factory()->create(['name' => 'Agencia Auditada']);
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'updated',
            'auditable_type' => Agency::class,
            'auditable_id' => $agency->id,
            'created_at' => now(),
        ]);
        AgencyImportRun::query()->create([
            'type' => 'shalom_sync',
            'status' => 'failed',
            'stage' => 'Finalizada',
            'chosen_original_name' => 'chosen-shalom.json',
            'created_by' => $actor->id,
            'total_received' => 20,
            'total_processed' => 20,
            'new_count' => 10,
            'updated_count' => 4,
            'unchanged_count' => 5,
            'error_count' => 1,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        Livewire::actingAs($actor)
            ->test(Dashboard::class)
            ->assertViewHas('recentActivity', fn ($activity): bool => $activity->count() >= 1 && $activity->first()->relationLoaded('actor'))
            ->assertSee($actor->name)
            ->assertSee('actualizó la agencia “Agencia Auditada”')
            ->assertSee('chosen-shalom.json')
            ->assertSee('Última sincronización Shalom')
            ->assertSee('Procesadas')
            ->assertSee('Nuevas')
            ->assertSee('Actualizadas')
            ->assertSee('Sin cambios')
            ->assertSee('Errores')
            ->assertSee('Fallida');
    }

    public function test_user_without_dashboard_permission_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertForbidden();
    }

    public function test_dashboard_handles_zero_agencies_and_missing_syncs_without_invalid_percentages(): void
    {
        $actor = $this->superAdmin();
        Agency::withoutEvents(fn () => Agency::withTrashed()->forceDelete());
        AgencyImportRun::query()->delete();

        Livewire::actingAs($actor)
            ->test(Dashboard::class)
            ->assertViewHas('agencyMetrics', fn (array $metrics): bool => $metrics['total'] === 0)
            ->assertViewHas('statusDistribution', fn (array $distribution): bool => collect($distribution)->every(
                fn (array $status): bool => $status['count'] === 0 && $status['percentage'] === 0.0,
            ))
            ->assertViewHas('lastSyncRun', null)
            ->assertSee('No se registraron agencias durante este periodo.')
            ->assertSee('No existen sincronizaciones.')
            ->assertSee('0.0%');
    }

    public function test_recent_activity_is_ordered_and_limited_to_six_real_events(): void
    {
        $actor = $this->superAdmin();
        $agencies = Agency::withoutEvents(fn () => Agency::factory()->count(8)->create());
        ActivityLog::query()->delete();

        foreach ($agencies as $index => $agency) {
            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'updated',
                'auditable_type' => Agency::class,
                'auditable_id' => $agency->id,
                'created_at' => now()->subMinutes(8 - $index),
            ]);
        }

        Livewire::actingAs($actor)
            ->test(Dashboard::class)
            ->assertViewHas('recentActivity', fn ($activity): bool => $activity->count() === 6
                && $activity->first()->auditable_id === $agencies->last()->id
                && $activity->last()->auditable_id === $agencies->get(2)->id)
            ->assertSee('Máximo 6');
    }

    public function test_sync_run_count_respects_selected_period(): void
    {
        $actor = $this->superAdmin();
        $createRun = function (int $daysAgo) use ($actor): void {
            $run = AgencyImportRun::query()->create([
                'type' => 'shalom_sync',
                'status' => 'completed',
                'stage' => 'Finalizada',
                'chosen_original_name' => 'chosen-'.$daysAgo.'.json',
                'created_by' => $actor->id,
                'total_received' => 1,
                'total_processed' => 1,
            ]);
            $run->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
        };

        $createRun(20);
        $createRun(2);

        Livewire::actingAs($actor)
            ->test(Dashboard::class)
            ->set('period', 7)
            ->assertViewHas('syncRunsInPeriod', 1)
            ->set('period', 30)
            ->assertViewHas('syncRunsInPeriod', 2);
    }
}
