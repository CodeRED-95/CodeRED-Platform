<?php

namespace Tests\Feature;

use App\Livewire\Admin\ApiTools\DniTester;
use App\Models\ApiClient;
use App\Models\DniRecord;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DniApiTesterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('dni.perudevs.enabled', false);
    }

    public function test_only_super_admin_can_access_tester(): void
    {
        $super = $this->roleUser('super-admin');
        $editor = $this->roleUser('editor');
        $viewer = $this->roleUser('viewer');

        $this->actingAs($super)->get('/admin/api-tools/dni')->assertOk()->assertSee('Probar API DNI');
        $this->actingAs($editor)->get('/admin/api-tools/dni')->assertForbidden();
        $this->actingAs($viewer)->get('/admin/api-tools/dni')->assertForbidden();
    }

    public function test_internal_mode_uses_local_flow_and_records_admin_test_without_secrets(): void
    {
        Http::fake();
        DniRecord::factory()->create(['dni' => '00123456', 'nombres' => 'LOCAL']);
        $super = $this->roleUser('super-admin');

        Livewire::actingAs($super)->test(DniTester::class)
            ->set('dni', '00123456')
            ->set('mode', 'internal')
            ->call('consult')
            ->assertHasNoErrors()
            ->assertSet('result.dni', '00123456')
            ->assertSet('technical.source', 'internal')
            ->assertSee('Base de datos interna')
            ->assertDontSee('DNI_PERUDEVS_API_KEY');

        Http::assertNothingSent();
        $this->assertDatabaseHas('api_request_logs', [
            'request_type' => 'admin_test',
            'service' => 'dni',
            'source' => 'internal',
            'local_database_hit' => true,
        ]);
        $this->assertDatabaseMissing('api_request_logs', ['request_type' => 'api']);
    }

    public function test_invalid_dni_is_not_processed(): void
    {
        Livewire::actingAs($this->roleUser('super-admin'))->test(DniTester::class)
            ->set('dni', '12-45678')
            ->call('consult')
            ->assertHasErrors(['dni']);

        $this->assertDatabaseCount('api_request_logs', 0);
    }

    public function test_endpoint_mode_checks_dni_ability_with_ephemeral_server_token(): void
    {
        DniRecord::factory()->create(['dni' => '12345678']);
        $client = ApiClient::factory()->create();
        $dniToken = $client->createToken('Token DNI de referencia', ['dni:consultar'])->accessToken;
        $agencyToken = $client->createToken('Token agencias', ['agencias:consultar'])->accessToken;
        $super = $this->roleUser('super-admin');

        Livewire::actingAs($super)->test(DniTester::class)
            ->set('dni', '12345678')
            ->set('mode', 'endpoint')
            ->set('tokenId', $dniToken->id)
            ->call('consult')
            ->assertHasNoErrors()
            ->assertSet('result.dni', '12345678')
            ->assertSet('technical.ability_verified', true)
            ->assertSet('technical.token_name', 'Token DNI de referencia');

        $this->assertDatabaseHas('api_request_logs', ['request_type' => 'admin_test', 'service' => 'dni']);
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Prueba administrativa efímera']);

        Livewire::actingAs($super)->test(DniTester::class)
            ->set('dni', '12345678')
            ->set('mode', 'endpoint')
            ->set('tokenId', $agencyToken->id)
            ->call('consult')
            ->assertSet('errorMessage', 'El token seleccionado no tiene el permiso dni:consultar.');
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
