<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
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
        // Verify que Dashboard carga sin error "Class App\Livewire\DB not found"
        $user = $this->superAdmin();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        // No hay error 500 por DB namespace
        $this->assertResponseIsSuccessful();
    }

    public function test_dashboard_ruc_metrics_usa_stats_service(): void
    {
        $user = $this->superAdmin();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertViewHas('rucMetrics');

        $metrics = $response['rucMetrics'];
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('records', $metrics);
        // records debe ser integer (no error)
        $this->assertIsInt($metrics['records']);
    }
}
