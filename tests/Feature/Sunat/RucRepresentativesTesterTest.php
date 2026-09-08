<?php

declare(strict_types=1);

namespace Tests\Feature\Sunat;

use App\DTO\SunatRepresentativeData;
use App\Livewire\Admin\ApiTools\RucRepresentativesTester;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Sunat\SunatRepresentativeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class RucRepresentativesTesterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_requiere_el_permiso_independiente_de_representantes(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(RucRepresentativesTester::class)->assertForbidden();
    }

    public function test_consulta_y_muestra_multiples_representantes(): void
    {
        $service = Mockery::mock(SunatRepresentativeService::class);
        // @phpstan-ignore-next-line
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

        Livewire::actingAs($this->userWithPermission('ruc.representantes'))
            ->test(RucRepresentativesTester::class)
            ->set('ruc', '20512528458')
            ->call('consult')
            ->assertHasNoErrors()
            ->assertSet('representantes.0.nombre', 'APELLIDOS NOMBRES')
            ->assertSet('representantes.1.cargo', 'APODERADO')
            ->assertSee('SEGUNDO REPRESENTANTE')
            ->assertSee('2021-03-01');
    }

    public function test_rechaza_un_ruc_invalido_antes_de_consultar_sunat(): void
    {
        $service = Mockery::mock(SunatRepresentativeService::class);
        $service->shouldNotReceive('find');
        $this->app->instance(SunatRepresentativeService::class, $service);

        Livewire::actingAs($this->userWithPermission('ruc.representantes'))
            ->test(RucRepresentativesTester::class)
            ->set('ruc', '123')
            ->call('consult')
            ->assertHasErrors(['ruc']);
    }

    private function userWithPermission(string $permissionSlug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'RUC REPRESENTANTES UI', 'slug' => 'ruc-representantes-ui-test']);
        $permission = Permission::query()->where('slug', $permissionSlug)->firstOrFail();
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
