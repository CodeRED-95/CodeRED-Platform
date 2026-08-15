<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_mobile_login_returns_token_roles_and_permissions(): void
    {
        $user = $this->superAdmin();

        $response = $this->postJson('/api/v1/mobile/login', [
            'email' => $user->email,
            'password' => 'Secret12345!',
            'device_name' => 'Android Samsung',
        ])->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame(['super-admin'], $response->json('data.roles'));
        $this->assertNotEmpty($response->json('data.permissions'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'codered-mobile - Android Samsung',
        ]);
    }

    public function test_mobile_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/v1/mobile/login', [
            'email' => 'usuario@dominio.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_mobile_me_requires_bearer_token(): void
    {
        $this->getJson('/api/v1/mobile/me')->assertStatus(401);
    }

    public function test_mobile_me_returns_authenticated_user_roles_and_permissions(): void
    {
        $user = $this->superAdmin();
        Sanctum::actingAs($user, ['mobile']);

        $this->getJson('/api/v1/mobile/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.roles.0', 'super-admin');
    }

    public function test_mobile_logout_revokes_only_current_token(): void
    {
        $user = $this->superAdmin();
        $tokenA = $user->createToken('codered-mobile - phone', ['mobile'])->plainTextToken;
        $tokenB = $user->createToken('codered-mobile - tablet', ['mobile'])->plainTextToken;

        $this->withToken($tokenA)
            ->postJson('/api/v1/mobile/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        auth()->forgetGuards();

        $this->withToken($tokenA)->getJson('/api/v1/mobile/me')->assertStatus(401);

        $this->withToken($tokenB)
            ->getJson('/api/v1/mobile/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_mobile_logout_does_not_invalidate_other_token(): void
    {
        $user = $this->superAdmin();
        $tokenA = $user->createToken('codered-mobile - phone', ['mobile'])->plainTextToken;
        $tokenB = $user->createToken('codered-mobile - tablet', ['mobile'])->plainTextToken;

        $this->withToken($tokenA)->postJson('/api/v1/mobile/logout')->assertOk();

        $this->withToken($tokenB)->getJson('/api/v1/mobile/me')->assertOk();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'is_active' => true,
            'password' => 'Secret12345!',
        ]);
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }
}
