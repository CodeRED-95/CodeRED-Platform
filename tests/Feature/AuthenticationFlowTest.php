<?php

namespace Tests\Feature;

use App\Livewire\Account\ChangePassword;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_protected_route(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect(route('dashboard'));
    }

    public function test_login_regenerates_session_and_redirects_to_intended_url(): void
    {
        $user = $this->activeUser();
        $token = 'csrf-session-regeneration';
        $this->withSession(['_token' => $token]);
        $previousSessionId = session()->getId();

        $this->get(route('admin.users.index'))->assertRedirect(route('login'));

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'Secret12345!',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_remember_login_sends_recaller_cookie(): void
    {
        $user = $this->activeUser();
        $token = 'csrf-remember';

        $response = $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'Secret12345!',
            'remember' => true,
        ]);

        $hasRecallerCookie = false;
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web_')) {
                $hasRecallerCookie = true;
                break;
            }
        }

        $this->assertTrue($hasRecallerCookie);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_normalizes_email_and_records_last_access(): void
    {
        $user = $this->activeUser(['email' => 'usuario@example.test']);
        $token = 'csrf-normalized-email';

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withSession(['_token' => $token])
            ->post(route('login.store'), [
                '_token' => $token,
                'email' => '  USUARIO@EXAMPLE.TEST  ',
                'password' => 'Secret12345!',
            ])->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('203.0.113.10', $user->last_login_ip);
    }

    public function test_role_does_not_prevent_active_user_from_authenticating(): void
    {
        $role = Role::query()->create([
            'name' => 'Consulta',
            'slug' => 'viewer',
            'is_system' => false,
        ]);
        $user = $this->activeUser();
        $user->roles()->attach($role);
        $token = 'csrf-role-login';

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'Secret12345!',
        ])->assertRedirect(route('profile.show'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('viewer'));
    }

    public function test_suspended_status_is_authoritative_even_when_legacy_flag_is_true(): void
    {
        $user = $this->activeUser([
            'status' => 'suspended',
            'is_active' => true,
        ]);
        $token = 'csrf-suspended';

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'Secret12345!',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_inactive_authenticated_session_is_terminated_by_middleware(): void
    {
        $user = $this->activeUser(['status' => 'suspended', 'is_active' => true]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_user_must_change_password_before_accessing_other_pages(): void
    {
        $user = $this->activeUser(['must_change_password' => true]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('account.change-password'));

        $this->get(route('account.change-password'))->assertOk();
    }

    public function test_forced_password_change_updates_password_and_releases_account(): void
    {
        $user = $this->activeUser(['must_change_password' => true]);
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'Secret12345!')
            ->set('password', 'NuevaClave12345')
            ->set('password_confirmation', 'NuevaClave12345')
            ->call('updatePassword')
            ->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NuevaClave12345', $user->password));
        $this->actingAs($user)->get(route('profile.show'))->assertOk();
    }

    public function test_login_redirects_user_with_forced_password_change(): void
    {
        $user = $this->activeUser(['must_change_password' => true]);
        $token = 'csrf-forced-password';

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'Secret12345!',
        ])->assertRedirect(route('account.change-password'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_authentication_and_redirects_to_login(): void
    {
        $user = $this->activeUser();
        $token = 'csrf-logout';

        $this->actingAs($user)
            ->withSession(['_token' => $token])
            ->post(route('logout'), ['_token' => $token])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_logout_requires_authentication_and_csrf(): void
    {
        $this->post(route('logout'))->assertStatus(419);

        $token = 'csrf-guest-logout';
        $this->withSession(['_token' => $token])
            ->post(route('logout'), ['_token' => $token])
            ->assertRedirect(route('login'));
    }

    public function test_login_validation_rejects_missing_and_invalid_fields_in_spanish(): void
    {
        $token = 'csrf-validation';

        $this->withSession(['_token' => $token])
            ->from(route('login'))
            ->post(route('login.store'), [
                '_token' => $token,
                'email' => 'correo-invalido',
                'password' => '',
                'remember' => 'invalid',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password', 'remember']);
    }

    private function activeUser(array $attributes = []): User
    {
        return User::factory()->create([
            'password' => Hash::make('Secret12345!'),
            'status' => 'active',
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
