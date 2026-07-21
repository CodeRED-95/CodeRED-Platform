<?php

namespace Tests\Feature;

use App\Livewire\Admin\ApiTools\RucTester;
use App\Models\ApiClient;
use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucRecord;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RucApiTesterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_super_admin_can_access_tester(): void
    {
        $this->actingAs($this->roleUser('super-admin'))->get('/admin/api-tools/ruc')->assertOk()->assertSee('Probar API RUC');
        $this->actingAs($this->roleUser('editor'))->get('/admin/api-tools/ruc')->assertForbidden();
        $this->actingAs($this->roleUser('viewer'))->get('/admin/api-tools/ruc')->assertForbidden();
    }

    public function test_internal_mode_uses_lookup_and_records_admin_test(): void
    {
        RucRecord::query()->create(['ruc' => '20123456789', 'razon_social' => 'EMPRESA LOCAL SAC']);

        Livewire::actingAs($this->roleUser('super-admin'))->test(RucTester::class)
            ->set('ruc', '20123456789')->set('mode', 'internal')->call('consult')
            ->assertHasNoErrors()->assertSet('result.ruc', '20123456789')
            ->assertSet('technical.source', 'internal')->assertSee('EMPRESA LOCAL SAC');

        $this->assertDatabaseHas('api_request_logs', ['request_type' => 'admin_test', 'service' => 'ruc', 'source' => 'internal']);
    }

    public function test_endpoint_mode_requires_ruc_ability_and_removes_ephemeral_token(): void
    {
        RucRecord::query()->create(['ruc' => '20123456789', 'razon_social' => 'EMPRESA LOCAL SAC']);
        $client = ApiClient::factory()->create();
        $rucToken = $client->createToken('Token RUC', ['ruc:consultar'])->accessToken;
        $dniToken = $client->createToken('Token DNI', ['dni:consultar'])->accessToken;
        $super = $this->roleUser('super-admin');

        Livewire::actingAs($super)->test(RucTester::class)
            ->set('ruc', '20123456789')->set('mode', 'endpoint')->set('tokenId', $rucToken->id)->call('consult')
            ->assertHasNoErrors()->assertSet('result.ruc', '20123456789')
            ->assertSet('technical.ability_verified', true)->assertSet('technical.token_name', 'Token RUC');

        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Prueba RUC efímera']);

        Livewire::actingAs($super)->test(RucTester::class)
            ->set('ruc', '20123456789')->set('mode', 'endpoint')->set('tokenId', $dniToken->id)->call('consult')
            ->assertSet('errorMessage', 'El token seleccionado no tiene el permiso ruc:consultar.');
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
