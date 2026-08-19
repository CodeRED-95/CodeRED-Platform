<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La portada no puede ser un muro.
 *
 * `/` es el dashboard y exige `dashboard.view`. Quien no lo tiene —el rol
 * viewer, por ejemplo— acababa en un 403 sin menú lateral, es decir sin forma
 * de llegar al listado de agencias o al mapa, que sí puede ver.
 */
class LandingTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCon(array $permisos): User
    {
        $usuario = User::factory()->create(['status' => 'active', 'is_active' => true]);

        $rol = Role::create(['slug' => 'landing-'.uniqid(), 'name' => 'Landing']);
        $rol->permissions()->sync(
            collect($permisos)
                ->map(fn (string $slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id)
                ->all()
        );
        $usuario->roles()->sync([$rol->id]);

        return $usuario;
    }

    public function test_el_viewer_llega_al_listado_de_agencias_en_vez_de_a_un_403(): void
    {
        $viewer = $this->usuarioCon(['agencies.view', 'agencies.map', 'platform.access']);

        $this->actingAs($viewer)
            ->get('/')
            ->assertRedirect(route('admin.agencies.index'));
    }

    public function test_el_viewer_abre_el_listado_y_el_mapa(): void
    {
        $viewer = $this->usuarioCon(['agencies.view', 'agencies.map', 'platform.access']);

        $this->actingAs($viewer)->get(route('admin.agencies.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.agencies.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.agencies.map'))->assertOk();
    }

    public function test_la_redireccion_funciona_despues_de_haber_visto_una_pantalla_livewire(): void
    {
        // Cuando un componente Livewire deniega, Livewire deja su propio
        // Redirector en el contenedor, y ese no devuelve una respuesta HTTP.
        // Con el helper redirect() la portada reventaba con un 500 -- pero solo
        // despues de ese 403, que es justo el orden que nadie prueba.
        $viewer = $this->usuarioCon(['agencies.view', 'agencies.map', 'platform.access']);

        $this->actingAs($viewer)->get(route('admin.agencies.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.agencies.create'))->assertForbidden();
        $this->actingAs($viewer)->get('/')->assertRedirect(route('admin.agencies.index'));
    }

    public function test_quien_si_tiene_el_dashboard_lo_sigue_viendo(): void
    {
        $usuario = $this->usuarioCon(['dashboard.view', 'platform.access']);

        $this->actingAs($usuario)->get('/')->assertOk();
    }

    public function test_sin_agencias_ni_dashboard_se_aterriza_en_el_perfil(): void
    {
        // El destino nunca puede ser otra pantalla prohibida: si lo fuera,
        // habriamos cambiado un 403 por otro.
        $usuario = $this->usuarioCon(['ruc.view', 'platform.access']);

        $this->actingAs($usuario)->get('/')->assertRedirect(route('profile.show'));
        $this->actingAs($usuario)->get(route('profile.show'))->assertOk();
    }
}
