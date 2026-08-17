<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ClientApplication;
use App\Livewire\Admin\Users\AccessAndSessions;
use App\Models\ClientSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ClientSessionManager;
use App\Services\Permissions\MobileAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Administración centralizada: accesos por aplicación y sesiones activas,
 * gobernados desde la ficha del usuario en Platform.
 */
class AdminAccessAndSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['name' => 'Super Administrador', 'slug' => 'super-admin']);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }

    private function member(): User
    {
        foreach (['platform.access', 'mobile.access', 'desktop.access'] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }

        return User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('contrasena-de-prueba'),
        ]);
    }

    private function openSession(User $user, ClientApplication $application): ClientSession
    {
        app(ClientSessionManager::class)->start($user, $application, ['device_name' => 'Equipo '.$application->value]);

        return ClientSession::query()->where('user_id', $user->id)->forApplication($application)->firstOrFail();
    }

    public function test_conceder_y_retirar_el_acceso_a_una_aplicacion(): void
    {
        $admin = $this->admin();
        $user = $this->member();

        $this->assertFalse($user->hasPermission('desktop.access'));

        Livewire::actingAs($admin)
            ->test(AccessAndSessions::class, ['user' => $user])
            ->call('toggleApplication', MobileAccess::DESKTOP_APP);

        $this->assertTrue($user->fresh()->hasPermission('desktop.access'));

        Livewire::actingAs($admin)
            ->test(AccessAndSessions::class, ['user' => $user->fresh()])
            ->call('toggleApplication', MobileAccess::DESKTOP_APP);

        $this->assertFalse($user->fresh()->hasPermission('desktop.access'));
    }

    public function test_retirar_el_acceso_cierra_las_sesiones_de_esa_aplicacion(): void
    {
        $admin = $this->admin();
        $user = $this->member();

        Livewire::actingAs($admin)
            ->test(AccessAndSessions::class, ['user' => $user])
            ->call('toggleApplication', MobileAccess::DESKTOP_APP)
            ->call('toggleApplication', MobileAccess::MOBILE_APP);

        $user = $user->fresh();
        $desktop = $this->openSession($user, ClientApplication::Desktop);
        $mobile = $this->openSession($user, ClientApplication::Mobile);

        Livewire::actingAs($admin)
            ->test(AccessAndSessions::class, ['user' => $user])
            ->call('toggleApplication', MobileAccess::DESKTOP_APP);

        // Sólo cae la sesión de la aplicación cuyo acceso se retiró.
        $this->assertNotNull($desktop->fresh()->revoked_at);
        $this->assertNull($mobile->fresh()->revoked_at);
    }

    public function test_el_administrador_cierra_una_sesion_concreta(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $session = $this->openSession($user, ClientApplication::Mobile);

        Livewire::actingAs($admin)
            ->test(AccessAndSessions::class, ['user' => $user])
            ->call('revokeSession', $session->uuid);

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertSame('revoked_by_admin', $session->fresh()->revocation_reason);
    }

    public function test_el_administrador_cierra_todas_las_sesiones(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $this->openSession($user, ClientApplication::Mobile);
        $this->openSession($user, ClientApplication::Desktop);

        Livewire::actingAs($admin)
            ->test(AccessAndSessions::class, ['user' => $user])
            ->call('revokeAllSessions');

        $this->assertSame(0, ClientSession::query()->active()->where('user_id', $user->id)->count());
    }

    public function test_cerrar_sesiones_no_toca_los_tokens_de_api(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $this->openSession($user, ClientApplication::Mobile);
        $user->createToken('n8n', ['ruc:consultar']);

        Livewire::actingAs($admin)
            ->test(AccessAndSessions::class, ['user' => $user])
            ->call('revokeAllSessions');

        // El token de integración sigue vivo: no representa a la persona.
        $this->assertSame(1, \Laravel\Sanctum\PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->where('kind', 'integration')
            ->count());
    }

    public function test_restablecer_la_contrasena_cierra_las_sesiones_de_cliente(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $this->openSession($user, ClientApplication::Mobile);
        $user->createToken('n8n', ['ruc:consultar']);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\Users\PasswordReset::class, ['user' => $user])
            ->call('resetPassword');

        $this->assertSame(0, ClientSession::query()->active()->where('user_id', $user->id)->count());

        // Y de nuevo: los tokens de API siguen su propia política.
        $this->assertSame(1, \Laravel\Sanctum\PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->where('kind', 'integration')
            ->count());
    }

    public function test_el_acceso_por_aplicacion_no_es_solicitable_por_el_interesado(): void
    {
        // La lista blanca de solicitudes sigue siendo sólo de módulos: nadie
        // puede pedirse a sí mismo la entrada a una aplicación.
        $this->assertFalse(MobileAccess::isRequestable(MobileAccess::DESKTOP_APP));
        $this->assertTrue(MobileAccess::isGrantable(MobileAccess::DESKTOP_APP));
        $this->assertTrue(MobileAccess::isRequestable(MobileAccess::RUC));
        $this->assertNotContains(MobileAccess::MOBILE_APP, MobileAccess::requestable());
    }
}
