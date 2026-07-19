<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyImportStatus;
use App\Modules\Agencies\Enums\AgencyImportStrategy;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::query()->create([
            'name' => 'Super Administrador',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);
        $actor = User::factory()->create();
        $actor->roles()->attach($role);

        return $actor;
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
            ->assertSee('Total usuarios')
            ->assertSee('Tendencia de agencias')
            ->assertSee('Distribución por estado')
            ->assertSee('Trasladadas');
    }

    public function test_dashboard_period_updates_real_user_and_agency_series(): void
    {
        $actor = $this->superAdmin();
        User::factory()->create(['created_at' => now()->subDays(8)]);
        User::factory()->create(['created_at' => now()->subDays(2)]);
        Agency::factory()->create(['created_at' => now()->subDays(6)]);

        Livewire::actingAs($actor)
            ->test(Dashboard::class)
            ->set('period', 7)
            ->assertViewHas('agencyTrend', fn (array $trend): bool => count($trend) === 7 && collect($trend)->sum('count') >= 1)
            ->assertViewHas('userMetrics', fn (array $metrics): bool => $metrics['new'] >= 2)
            ->assertSee('Últimos 7 días');
    }

    public function test_dashboard_shows_real_activity_and_complete_last_import(): void
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
        AgencyImport::query()->create([
            'user_id' => $actor->id,
            'original_filename' => 'agencias.json',
            'stored_filename' => 'imports/agencias.json',
            'file_type' => 'json',
            'status' => AgencyImportStatus::CompletedWithErrors,
            'strategy' => AgencyImportStrategy::UpdateExisting,
            'total_rows' => 20,
            'valid_rows' => 19,
            'imported_rows' => 10,
            'updated_rows' => 4,
            'skipped_rows' => 5,
            'failed_rows' => 1,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        Livewire::actingAs($actor)
            ->test(Dashboard::class)
            ->assertViewHas('recentActivity', fn ($activity): bool => $activity->count() >= 1 && $activity->first()->relationLoaded('actor'))
            ->assertSee($actor->name)
            ->assertSee('actualizó un registro')
            ->assertSee('agencias.json')
            ->assertSee('Procesados')
            ->assertSee('Importados')
            ->assertSee('Actualizados')
            ->assertSee('Ignorados')
            ->assertSee('Errores');
    }

    public function test_dashboard_does_not_expose_administrative_metrics_without_permissions(): void
    {
        $user = User::factory()->create();
        Agency::factory()->create(['name' => 'Agencia confidencial']);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertViewHas('canViewAgencies', false)
            ->assertViewHas('canViewUsers', false)
            ->assertSee('No tienes indicadores disponibles')
            ->assertDontSee('Agencia confidencial')
            ->assertDontSee('Total usuarios');
    }
}
