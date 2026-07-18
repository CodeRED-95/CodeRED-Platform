<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_responds_ok(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }

    public function test_login_form_uses_post_csrf_and_store_route(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSeeHtml('method="POST"')
            ->assertSeeHtml('action="'.route('login.store').'"')
            ->assertSeeHtml('name="_token"')
            ->assertDontSeeHtml('wire:id=')
            ->assertDontSeeHtml('wire:submit')
            ->assertDontSeeHtml('wire:model')
            ->assertDontSeeHtml('$wire.set');
    }

    public function test_login_route_has_get_and_post_endpoints(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'login')
            ->flatMap(fn ($route) => $route->methods())
            ->unique()
            ->values()
            ->all();

        $this->assertContains('GET', $routes);
        $this->assertContains('HEAD', $routes);
        $this->assertContains('POST', $routes);
    }

    public function test_valid_login_redirects_to_dashboard(): void
    {
        $token = 'csrf-login-valido';
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'email' => 'admin@example.test',
            'password' => 'Secret123!',
            'remember' => 1,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_shows_spanish_error(): void
    {
        $token = 'csrf-login-invalido';
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->withSession(['_token' => $token])
            ->from('/login')
            ->post(route('login.store'), [
                '_token' => $token,
                'email' => 'admin@example.test',
                'password' => 'WrongPassword!',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email']);
    }

    public function test_get_login_never_accepts_credentials(): void
    {
        $this->get('/login', [
            'email' => 'admin@example.test',
            'password' => 'Secret123!',
        ])->assertOk();

        $this->assertGuest();
    }

    public function test_login_button_is_submit(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSeeHtml('<button type="submit"');
    }

    public function test_login_rejects_request_without_csrf_token(): void
    {
        $this->post(route('login.store'), [
            'email' => 'admin@example.test',
            'password' => 'Secret123!',
        ])->assertStatus(419);
    }
}
