<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Form;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El nombre anterior lo escribe el flujo de renombrado y el campo es de solo
 * lectura, asi que sin este boton no habia forma de retirarlo desde el panel.
 */
class AgencyClearOldNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_super_admin_retira_el_nombre_anterior_al_guardar(): void
    {
        $agency = Agency::factory()->create(['name' => 'AV EJERCITO', 'old_name' => 'AVENIDA EJERCITO']);

        Livewire::actingAs($this->admin())
            ->test(Form::class, ['agency' => $agency])
            ->assertSet('old_name', 'AVENIDA EJERCITO')
            ->call('clearOldName')
            ->assertHasNoErrors()
            ->assertSet('old_name', null)
            ->call('save');

        $this->assertNull($agency->fresh()->old_name);
        // El nombre vigente no se toca.
        $this->assertSame('AV EJERCITO', $agency->fresh()->name);
    }

    public function test_el_boton_no_escribe_por_si_solo(): void
    {
        // Se consolida al guardar: si el usuario se arrepiente y sale sin
        // guardar, el nombre anterior sigue donde estaba.
        $agency = Agency::factory()->create(['old_name' => 'AVENIDA EJERCITO']);

        Livewire::actingAs($this->admin())
            ->test(Form::class, ['agency' => $agency])
            ->call('clearOldName')
            ->assertSet('old_name', null);

        $this->assertSame('AVENIDA EJERCITO', $agency->fresh()->old_name);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador']);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }
}
