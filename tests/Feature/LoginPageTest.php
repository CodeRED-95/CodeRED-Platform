<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_responds_ok(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_login_component_renders_livewire_bindings_and_autocomplete(): void
    {
        Livewire::test(Login::class)
            ->assertSeeHtml('wire:id=')
            ->assertSeeHtml('wire:model.live="email"')
            ->assertSeeHtml('wire:model.live="password"')
            ->assertSeeHtml('wire:submit.prevent="authenticate"')
            ->assertSeeHtml('autocomplete="username"')
            ->assertSeeHtml('autocomplete="current-password"');
    }

    public function test_login_form_does_not_use_get_or_action(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSeeHtml('wire:submit.prevent="authenticate"')
            ->assertDontSeeHtml('method="GET"')
            ->assertDontSeeHtml('action="/login"')
            ->assertDontSeeHtml('$wire.set');
    }

    public function test_login_validation_messages_are_in_spanish(): void
    {
        Livewire::test(Login::class)
            ->call('authenticate')
            ->assertHasErrors(['email' => 'required', 'password' => 'required'])
            ->assertSee('El campo correo electrónico es obligatorio.')
            ->assertSee('El campo contraseña es obligatorio.');
    }

    public function test_valid_login_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'admin@example.test')
            ->set('password', 'Secret123!')
            ->set('remember', true)
            ->call('authenticate')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_button_is_submit(): void
    {
        $html = Livewire::test(Login::class)->html();

        $this->assertStringContainsString('<button type="submit"', $html);
    }

    public function test_invalid_login_shows_spanish_failed_message(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'admin@example.test')
            ->set('password', 'WrongPassword!')
            ->call('authenticate')
            ->assertHasErrors(['email'])
            ->assertSee('Las credenciales ingresadas no son válidas.');
    }
}
