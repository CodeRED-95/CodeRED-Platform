<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\DniRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDelegatedUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DniRecord::factory()->create(['dni' => '12345678']);
    }

    public function test_authorized_client_with_valid_active_permitted_user_delegates_successfully(): void
    {
        $client = $this->delegatingClient();
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;
        $user = $this->userWithPermission('declaracion-jurada.view');

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id])
            ->getJson('/api/v1/dni/12345678')
            ->assertOk()
            ->assertJsonPath('data.dni', '12345678');

        $log = ApiRequestLog::query()->latest('id')->first();
        $this->assertSame($client->id, $log->api_client_id);
        $this->assertSame($user->id, $log->delegated_user_id);
        $this->assertFalse($log->is_duplicate_request);
    }

    public function test_client_without_delegation_flag_cannot_delegate(): void
    {
        $client = ApiClient::factory()->create(['can_delegate_users' => false]);
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;
        $user = $this->userWithPermission('declaracion-jurada.view');

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id])
            ->getJson('/api/v1/dni/12345678')
            ->assertForbidden();

        $log = ApiRequestLog::query()->latest('id')->first();
        $this->assertNull($log->delegated_user_id);
        $this->assertSame(403, $log->status_code);
    }

    public function test_nonexistent_delegated_user_is_rejected(): void
    {
        $client = $this->delegatingClient();
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => '999999'])
            ->getJson('/api/v1/dni/12345678')
            ->assertForbidden();
    }

    public function test_inactive_delegated_user_is_rejected(): void
    {
        $client = $this->delegatingClient();
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;
        $user = $this->userWithPermission('declaracion-jurada.view');
        $user->forceFill(['status' => 'suspended'])->save();

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id])
            ->getJson('/api/v1/dni/12345678')
            ->assertForbidden();
    }

    public function test_deleted_delegated_user_is_rejected(): void
    {
        $client = $this->delegatingClient();
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;
        $user = $this->userWithPermission('declaracion-jurada.view');
        $user->delete();

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id])
            ->getJson('/api/v1/dni/12345678')
            ->assertForbidden();
    }

    public function test_user_without_required_permission_is_rejected_but_still_audited(): void
    {
        $client = $this->delegatingClient();
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;
        $user = User::factory()->create(['status' => 'active']);

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id])
            ->getJson('/api/v1/dni/12345678')
            ->assertForbidden();

        $log = ApiRequestLog::query()->latest('id')->first();
        $this->assertSame($user->id, $log->delegated_user_id, 'el intento rechazado igual debe quedar atribuido a ese usuario');
        $this->assertSame(403, $log->status_code);
    }

    public function test_request_without_delegation_header_still_works_and_logs_no_user(): void
    {
        $client = $this->delegatingClient();
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/dni/12345678')->assertOk();

        $log = ApiRequestLog::query()->latest('id')->first();
        $this->assertNull($log->delegated_user_id);
    }

    public function test_a_non_delegating_client_sending_the_header_is_rejected_even_if_the_user_is_valid(): void
    {
        $normalClient = ApiClient::factory()->create(['can_delegate_users' => false]);
        $token = $normalClient->createToken('Otro cliente', ['dni:consultar'])->plainTextToken;
        $user = $this->userWithPermission('declaracion-jurada.view');

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id])
            ->getJson('/api/v1/dni/12345678')
            ->assertForbidden();
    }

    public function test_duplicate_request_id_is_flagged_but_not_blocked(): void
    {
        $client = $this->delegatingClient();
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;
        $user = $this->userWithPermission('declaracion-jurada.view');

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id, 'X-Request-Id' => 'req-fixed-123'])
            ->getJson('/api/v1/dni/12345678')
            ->assertOk();
        auth()->forgetGuards();
        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id, 'X-Request-Id' => 'req-fixed-123'])
            ->getJson('/api/v1/dni/12345678')
            ->assertOk();

        $logs = ApiRequestLog::query()->where('request_id', 'req-fixed-123')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertFalse($logs->first()->is_duplicate_request);
        $this->assertTrue($logs->last()->is_duplicate_request);
    }

    public function test_two_api_clients_keep_separate_audit_trails(): void
    {
        $clientA = $this->delegatingClient('Cliente A');
        $clientB = ApiClient::factory()->create(['name' => 'Cliente B', 'can_delegate_users' => false]);
        $tokenA = $clientA->createToken('A', ['dni:consultar'])->plainTextToken;
        $tokenB = $clientB->createToken('B', ['dni:consultar'])->plainTextToken;

        $this->withToken($tokenA)->getJson('/api/v1/dni/12345678')->assertOk();
        auth()->forgetGuards();
        $this->withToken($tokenB)->getJson('/api/v1/dni/12345678')->assertOk();

        $this->assertSame(
            [$clientA->id, $clientB->id],
            ApiRequestLog::query()->orderBy('id')->pluck('api_client_id')->all()
        );
    }

    public function test_response_contract_is_unchanged_by_delegation(): void
    {
        $client = $this->delegatingClient();
        $token = $client->createToken('Bridge', ['dni:consultar'])->plainTextToken;
        $user = $this->userWithPermission('declaracion-jurada.view');

        $response = $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->id])
            ->getJson('/api/v1/dni/12345678');

        $response->assertOk()->assertJsonStructure([
            'data' => ['dni', 'nombres', 'apellido_paterno', 'apellido_materno', 'nombre_completo', 'genero', 'fecha_nacimiento', 'edad', 'codigo_verificacion'],
            'success', 'meta' => ['source'],
        ]);
        $this->assertArrayNotHasKey('usage', $response->json());
    }

    private function delegatingClient(string $name = 'Declaración Jurada Shalom'): ApiClient
    {
        return ApiClient::factory()->create([
            'name' => $name,
            'can_delegate_users' => true,
            'delegation_permission' => 'declaracion-jurada.view',
        ]);
    }

    private function userWithPermission(string $slug): User
    {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        $role = Role::query()->create(['slug' => 'delegation-test-'.uniqid(), 'name' => 'Rol de prueba de delegación']);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }
}
