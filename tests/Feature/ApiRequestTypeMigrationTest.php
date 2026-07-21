<?php

namespace Tests\Feature;

use App\Core\Api\Enums\ApiRequestType;
use App\Livewire\Admin\ApiTokens\Index;
use App\Models\ApiRequestLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ApiRequestTypeMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_column_default_enum_cast_and_existing_inserts_are_api(): void
    {
        $this->assertTrue(Schema::hasColumn('api_request_logs', 'request_type'));
        $id = DB::table('api_request_logs')->insertGetId([
            'service' => 'agencias', 'endpoint' => '/api/v1/agencias', 'method' => 'GET', 'status_code' => 200,
        ]);
        $log = ApiRequestLog::query()->findOrFail($id);
        $this->assertSame(ApiRequestType::Api, $log->request_type);
        $this->assertSame('api', DB::table('api_request_logs')->where('id', $id)->value('request_type'));
    }

    public function test_token_counters_only_include_normal_api_requests(): void
    {
        $super = $this->superAdmin();
        $token = $super->createToken('Contadores', ['agencias:consultar', 'dni:consultar'])->accessToken;
        foreach ([
            [ApiRequestType::Api, 'agencias', 200],
            [ApiRequestType::Api, 'dni', 404],
            [ApiRequestType::AdminTest, 'dni', 200],
            [ApiRequestType::ProviderTest, 'dni', 503],
        ] as [$type, $service, $status]) {
            ApiRequestLog::query()->create([
                'token_id' => $token->getKey(), 'request_type' => $type, 'service' => $service,
                'endpoint' => '/test', 'method' => 'GET', 'status_code' => $status, 'created_at' => now(),
            ]);
        }

        Livewire::actingAs($super)->test(Index::class)
            ->assertSee('Consultas: 2 · Agencias 1 · DNI 1')
            ->assertSee('Exitosas: 1 · Errores: 1');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        return $user;
    }
}
