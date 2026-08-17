<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ClientApplication;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ClientSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La ficha del usuario debe seguir renderizando con el bloque nuevo dentro.
 * Un componente Livewire mal incrustado no rompe sus propias pruebas: rompe la
 * página que lo aloja, y eso sólo se ve pidiéndola entera.
 */
class UserShowPageRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ficha_del_usuario_muestra_accesos_y_sesiones(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->sync([Role::query()->create(['name' => 'Super Administrador', 'slug' => 'super-admin'])->id]);

        $member = User::factory()->create(['status' => 'active']);
        app(ClientSessionManager::class)->start($member, ClientApplication::Mobile, ['device_name' => 'Galaxy S24']);

        $this->actingAs($admin->fresh())
            ->get(route('admin.users.show', $member))
            ->assertOk()
            ->assertSee('Accesos')
            ->assertSee('Aplicaciones')
            // Los modulos de consulta se gobiernan aqui, no solo desde Mobile.
            ->assertSee('Módulos de consulta')
            ->assertSee('Consulta DNI')
            ->assertSee('Consulta RUC')
            ->assertSee('Sesiones activas')
            ->assertSee('CodeRED Mobile')
            ->assertSee('Galaxy S24')
            // La separación entre sesiones de usuario y tokens de API.
            ->assertSee('tokens de API');
    }
}
