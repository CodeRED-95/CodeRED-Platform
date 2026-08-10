<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ConfiguredAdminSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Super administrador definido por .env y rol viewer del registro público.
 *
 * No crea solicitudes de token ni dispara webhooks, n8n o Telegram.
 */
class SuperAdminAndViewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Fija las variables de entorno del admin. El servicio prioriza getenv(),
     * así que se establecen ahí para que el test sea determinista y no dependa
     * del .env real del contenedor.
     */
    private function configureDevAdmin(): void
    {
        putenv('DEV_ADMIN_NAME=Admin Configurado');
        putenv('DEV_ADMIN_EMAIL=admin-config@codered.lat');
        putenv('DEV_ADMIN_PASSWORD=Sup3rS3cret!2026');
    }

    protected function tearDown(): void
    {
        putenv('DEV_ADMIN_NAME');
        putenv('DEV_ADMIN_EMAIL');
        putenv('DEV_ADMIN_PASSWORD');

        parent::tearDown();
    }

    public function test_configured_admin_is_created_as_super_admin_from_env(): void
    {
        $this->configureDevAdmin();

        $result = app(ConfiguredAdminSyncService::class)->sync();

        $this->assertTrue($result['created']);
        $user = User::query()->where('email', 'admin-config@codered.lat')->firstOrFail();
        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertTrue($user->isActive());

        // La contraseña nunca se guarda en claro.
        $this->assertNotSame('Sup3rS3cret!2026', $user->password);
    }

    public function test_syncing_the_configured_admin_twice_is_idempotent(): void
    {
        $this->configureDevAdmin();

        app(ConfiguredAdminSyncService::class)->sync();
        $second = app(ConfiguredAdminSyncService::class)->sync();

        $this->assertFalse($second['created']);
        $this->assertSame(1, User::query()->where('email', 'admin-config@codered.lat')->count());
        $this->assertTrue($second['user']->hasRole('super-admin'));
    }

    /**
     * El formulario lleva CSRF (middleware web), así que se envía el token de
     * sesión igual que lo haría el navegador.
     */
    private function register(array $payload)
    {
        $token = 'csrf-token-registro';

        return $this->withSession(['_token' => $token])
            ->post('/register', array_merge($payload, ['_token' => $token]));
    }

    public function test_public_registration_creates_a_viewer_never_a_super_admin(): void
    {
        $response = $this->register([
            'name' => 'Nuevo Publico',
            'email' => 'publico@correo.test',
            'password' => 'Contrasena123456!',
            'password_confirmation' => 'Contrasena123456!',
        ]);

        $response->assertRedirect();

        $user = User::query()->where('email', 'publico@correo.test')->firstOrFail();
        $this->assertTrue($user->hasRole('viewer'));
        $this->assertFalse($user->hasRole('super-admin'));
        $this->assertFalse($user->hasRole('editor'));
    }

    public function test_public_viewer_has_no_administrative_permissions(): void
    {
        $this->register([
            'name' => 'Otro Publico',
            'email' => 'otro@correo.test',
            'password' => 'Contrasena123456!',
            'password_confirmation' => 'Contrasena123456!',
        ])->assertRedirect();

        $user = User::query()->where('email', 'otro@correo.test')->firstOrFail();

        // Puede lo mínimo:
        $this->assertTrue($user->hasPermission('agencies.view'));
        $this->assertTrue($user->hasPermission('agencies.map'));
        $this->assertTrue($user->hasPermission('shalom-recordar.view-own'));

        // No puede nada administrativo:
        foreach ([
            'agencies.create', 'agencies.update', 'agencies.delete',
            'agencies.backup.view', 'agencies.backup.restore',
            'users.view', 'users.create',
            'api-tokens.view', 'settings.dni.update',
            'dashboard.view',
        ] as $permission) {
            $this->assertFalse($user->hasPermission($permission), "viewer no debe tener {$permission}");
        }
    }

    public function test_viewer_is_blocked_from_admin_screens_in_the_backend(): void
    {
        $role = Role::query()->where('slug', 'viewer')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user);

        // Permitido
        $this->get(route('admin.agencies.index'))->assertOk();
        $this->get(route('admin.agencies.map'))->assertOk();
        $this->get(route('profile.show'))->assertOk();

        // Bloqueado en el backend, no solo ocultando botones
        $this->get(route('admin.agencies.create'))->assertForbidden();
        $this->get(route('admin.agencies.backups.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.api-tokens.index'))->assertForbidden();
        $this->get(route('admin.settings.ubigeos'))->assertForbidden();
        $this->get(route('dashboard'))->assertForbidden();
    }
}
