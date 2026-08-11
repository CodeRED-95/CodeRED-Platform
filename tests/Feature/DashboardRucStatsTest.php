<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardRucStatsTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    public function test_dashboard_no_class_not_found_error(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertDontSee('Class App\\Livewire\\DB not found');
    }

    public function test_dashboard_ruc_metrics_usa_stats_service(): void
    {
        $user = $this->superAdmin();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertViewHas('rucMetrics', fn (array $metrics): bool => array_key_exists('backups', $metrics))
            ->assertSee('Registros RUC')
            ->assertSee('Backups RUC')
            ->assertSee('Consultas hoy');
    }
}
