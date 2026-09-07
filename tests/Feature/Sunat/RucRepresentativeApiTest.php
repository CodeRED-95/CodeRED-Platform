<?php

declare(strict_types=1);

namespace Tests\Feature\Sunat;

use App\DTO\SunatRepresentativeData;
use App\Exceptions\SunatRepresentativeException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Sunat\SunatRepresentativeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class RucRepresentativeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithPermission(string $permissionSlug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'RUC REP', 'slug' => 'ruc-rep-test']);
        $permission = Permission::query()->where('slug', $permissionSlug)->firstOrFail();
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_rechaza_ruc_invalido_con_422(): void
    {
        Sanctum::actingAs($this->userWithPermission('ruc.representantes'), ['ruc:representantes']);
        $response = $this
            ->getJson('/api/v1/ruc/123/representantes');

        $response->assertStatus(422);
    }

    public function test_requiere_autenticacion(): void
    {
        $response = $this->getJson('/api/v1/ruc/20512528458/representantes');
        $response->assertStatus(401);
    }

    public function test_requiere_permiso_especifico(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['ruc:consultar']);
        $response = $this->getJson('/api/v1/ruc/20512528458/representantes');
        $response->assertStatus(403);
    }

    public function test_devuelve_representantes_y_cachea_el_resultado(): void
    {
        Cache::flush();
        config(['sunat.representatives.cache_enabled' => true, 'sunat.representatives.cache_ttl' => 3600]);

        $service = Mockery::mock(SunatRepresentativeService::class);
        $service->shouldReceive('find')->once()->with('20512528458')->andReturn([
            'data' => [
                new SunatRepresentativeData('DNI', '12345678', 'APELLIDOS NOMBRES', 'GERENTE GENERAL', '2020-01-15'),
                new SunatRepresentativeData('CE', 'X1234567', 'SEGUNDO REPRESENTANTE', 'APODERADO', '2021-03-01'),
            ],
            'cached' => false,
            'source' => 'sunat',
            'consulted_at' => '2026-09-07T12:00:00Z',
        ]);
        $this->app->instance(SunatRepresentativeService::class, $service);

        $user = $this->userWithPermission('ruc.representantes');
        Sanctum::actingAs($user, ['ruc:representantes']);
        $response = $this->getJson('/api/v1/ruc/20512528458/representantes');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ruc', '20512528458')
            ->assertJsonCount(2, 'data.representantes')
            ->assertJsonPath('meta.cached', false);
    }

    public function test_devuelve_404_si_sunat_no_tiene_representantes(): void
    {
        $service = Mockery::mock(SunatRepresentativeService::class);
        $service->shouldReceive('find')->once()->andReturn([
            'data' => null,
            'cached' => false,
            'source' => 'sunat',
            'consulted_at' => now()->toISOString(),
        ]);
        $this->app->instance(SunatRepresentativeService::class, $service);
        Sanctum::actingAs($this->userWithPermission('ruc.representantes'), ['ruc:representantes']);

        $this->getJson('/api/v1/ruc/20512528458/representantes')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_mapea_timeout_y_html_invalido_como_errores_sin_exponer_el_html(): void
    {
        $service = Mockery::mock(SunatRepresentativeService::class);
        $service->shouldReceive('find')->once()->andThrow(new ConnectionException('Connection timed out'));
        $this->app->instance(SunatRepresentativeService::class, $service);
        Sanctum::actingAs($this->userWithPermission('ruc.representantes'), ['ruc:representantes']);

        $this->getJson('/api/v1/ruc/20512528458/representantes')
            ->assertStatus(504)
            ->assertJsonMissingPath('html');

        $service = Mockery::mock(SunatRepresentativeService::class);
        $service->shouldReceive('find')->once()->andThrow(new SunatRepresentativeException('<html>interno</html>'));
        $this->app->instance(SunatRepresentativeService::class, $service);

        $this->getJson('/api/v1/ruc/20512528458/representantes')
            ->assertStatus(502)
            ->assertJsonMissingPath('html');
    }
}
