<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

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

        $role = Role::query()->create([
            'name' => 'Super Administrador',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);
        $actor = User::factory()->create();
        $actor->roles()->attach($role);
        User::factory()->create();
        User::factory()->create(['created_at' => now()->subDays(40)]);

        Agency::factory()->create(['status' => AgencyStatus::Active]);
        Agency::factory()->create(['status' => AgencyStatus::Inactive]);
        Agency::factory()->create(['status' => AgencyStatus::TemporarilyClosed]);
        Agency::factory()->create(['status' => AgencyStatus::UnderReview]);
        Agency::factory()->create([
            'status' => AgencyStatus::Moved,
            'has_moved' => true,
            'moved_to_address' => 'Nueva ubicación',
        ]);

        Livewire::actingAs($actor)
            ->test(Dashboard::class)
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
            ->assertViewHas('agencyTrend', fn (array $trend): bool => count($trend) === 7)
            ->assertSee('Total usuarios')
            ->assertSee('Agencias creadas recientemente')
            ->assertSee('Distribución por estado')
            ->assertSee('Agencias trasladadas');
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
