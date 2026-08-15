<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeclaracionJuradaSetupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_creates_client_and_issues_token_with_expected_abilities(): void
    {
        $this->assertDatabaseMissing('api_clients', ['name' => 'Declaración Jurada Shalom']);

        $exitCode = Artisan::call('declaracion-jurada:setup');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Token emitido', $output);

        $client = ApiClient::query()->where('name', 'Declaración Jurada Shalom')->firstOrFail();
        $token = $client->tokens()->whereNull('revoked_at')->firstOrFail();
        $this->assertEqualsCanonicalizing(['dni:consultar', 'agencias:consultar', 'declaraciones:gestionar'], $token->abilities);
    }

    public function test_running_again_with_correct_abilities_is_a_noop(): void
    {
        Artisan::call('declaracion-jurada:setup');
        $client = ApiClient::query()->where('name', 'Declaración Jurada Shalom')->firstOrFail();
        $tokenId = $client->tokens()->whereNull('revoked_at')->firstOrFail()->id;

        $exitCode = Artisan::call('declaracion-jurada:setup');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('Token emitido', $output);
        $this->assertSame($tokenId, $client->tokens()->whereNull('revoked_at')->firstOrFail()->id);
    }

    public function test_outdated_abilities_trigger_automatic_reissue_without_reissue_flag(): void
    {
        // Simula una instalación previa a la integración de agencias: un
        // ApiClient con un token que solo tiene dni:consultar.
        $client = ApiClient::factory()->create(['name' => 'Declaración Jurada Shalom']);
        $oldToken = $client->createToken('declaracion-jurada-dni-bridge', ['dni:consultar']);

        $exitCode = Artisan::call('declaracion-jurada:setup');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Token emitido', $output);
        $this->assertStringContainsString('abilities desactualizadas', $output);

        $this->assertNotNull($oldToken->accessToken->fresh()->revoked_at);
        $newToken = $client->tokens()->whereNull('revoked_at')->firstOrFail();
        $this->assertEqualsCanonicalizing(['dni:consultar', 'agencias:consultar', 'declaraciones:gestionar'], $newToken->abilities);
    }

    public function test_reissue_flag_forces_a_new_token_even_if_abilities_already_match(): void
    {
        Artisan::call('declaracion-jurada:setup');
        $client = ApiClient::query()->where('name', 'Declaración Jurada Shalom')->firstOrFail();
        $firstTokenId = $client->tokens()->whereNull('revoked_at')->firstOrFail()->id;

        $exitCode = Artisan::call('declaracion-jurada:setup', ['--reissue' => true]);

        $this->assertSame(0, $exitCode);
        $newToken = $client->tokens()->whereNull('revoked_at')->firstOrFail();
        $this->assertNotSame($firstTokenId, $newToken->id);
    }

    public function test_setup_enables_user_delegation_for_the_bridge(): void
    {
        Artisan::call('declaracion-jurada:setup');

        $client = ApiClient::query()->where('name', 'Declaración Jurada Shalom')->firstOrFail();

        // Sin esto, ResolveDelegatedUser rechaza X-CodeRED-User-Id y ninguna
        // declaración del paquete React podría atribuirse a su autor.
        $this->assertTrue($client->canDelegateUsers());
        $this->assertSame('declaracion-jurada.view', $client->delegation_permission);
    }

    public function test_setup_repairs_an_existing_client_without_delegation(): void
    {
        // Instalación anterior a la integración de declaraciones: el cliente ya
        // existe, así que firstOrCreate no lo tocaría.
        ApiClient::factory()->create([
            'name' => 'Declaración Jurada Shalom',
            'can_delegate_users' => false,
            'delegation_permission' => null,
        ]);

        Artisan::call('declaracion-jurada:setup');

        $client = ApiClient::query()->where('name', 'Declaración Jurada Shalom')->firstOrFail();
        $this->assertTrue($client->canDelegateUsers());
        $this->assertSame('declaracion-jurada.view', $client->delegation_permission);
    }

    public function test_setup_creates_the_permission_and_assigns_it_to_the_viewer_role(): void
    {
        // No se asume una base vacía: algunos tests de otros archivos usan
        // DatabaseTruncation (commits reales, sin rollback) en vez de
        // RefreshDatabase, así que declaracion-jurada.view podría ya existir
        // por una corrida anterior dentro de la misma suite. Lo que importa
        // es el estado DESPUÉS de correr el comando, no el de antes.
        $this->seed(RolesSeeder::class);

        Artisan::call('declaracion-jurada:setup');

        $permission = Permission::query()->where('slug', 'declaracion-jurada.view')->firstOrFail();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $this->assertTrue($viewer->permissions()->whereKey($permission->id)->exists());
    }

    public function test_setup_does_not_remove_other_permissions_already_on_viewer(): void
    {
        $this->seed(RolesSeeder::class);
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        // Slug claramente ficticio (no uno real del catálogo, como
        // agencies.view) para no chocar con datos ya confirmados por otro
        // test de la suite que use DatabaseTruncation — mismo criterio que
        // RolePermissionSeederIdempotencyTest::test_role_permission_sync_does_not_remove_permissions_from_other_roles.
        $preExisting = Permission::query()->firstOrCreate(
            ['slug' => 'custom.setup-command-test.marker'],
            ['name' => 'Marcador de prueba de declaracion-jurada:setup']
        );
        $viewer->permissions()->syncWithoutDetaching([$preExisting->id]);

        Artisan::call('declaracion-jurada:setup');

        $viewer->refresh();
        $this->assertTrue($viewer->permissions()->whereKey($preExisting->id)->exists());
        $newPermission = Permission::query()->where('slug', 'declaracion-jurada.view')->firstOrFail();
        $this->assertTrue($viewer->permissions()->whereKey($newPermission->id)->exists());
    }

    public function test_running_setup_twice_does_not_duplicate_the_role_permission_pivot_row(): void
    {
        $this->seed(RolesSeeder::class);

        Artisan::call('declaracion-jurada:setup');
        Artisan::call('declaracion-jurada:setup');

        $permission = Permission::query()->where('slug', 'declaracion-jurada.view')->firstOrFail();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $rows = DB::table('permission_role')
            ->where('permission_id', $permission->id)
            ->where('role_id', $viewer->id)
            ->count();
        $this->assertSame(1, $rows);
    }

    public function test_a_user_who_already_had_the_viewer_role_gets_access_without_any_per_user_change(): void
    {
        $this->seed(RolesSeeder::class);
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        // Se garantiza el punto de partida ("viewer todavía no tiene el
        // permiso") de forma explícita en vez de asumir una base vacía: un
        // test con DatabaseTruncation en otro archivo de la suite puede
        // haber dejado declaracion-jurada.view ya asignado por una corrida
        // anterior. Se desasigna aquí si hiciera falta para que el ANTES
        // de esta prueba sea determinista.
        $existingPermission = Permission::query()->where('slug', 'declaracion-jurada.view')->first();
        if ($existingPermission) {
            $viewer->permissions()->detach($existingPermission->id);
        }

        // El usuario recibe el rol ANTES de que exista el permiso — simula
        // exactamente el reporte del bug: una cuenta viewer creada antes de
        // este arreglo, sin ninguna reasignación manual posterior.
        $user = User::factory()->create();
        $user->roles()->attach($viewer);
        $this->assertFalse($user->hasPermission('declaracion-jurada.view'));

        Artisan::call('declaracion-jurada:setup');

        $this->assertTrue($user->fresh()->hasPermission('declaracion-jurada.view'));
    }
}
