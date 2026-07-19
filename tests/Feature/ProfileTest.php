<?php

namespace Tests\Feature;

use App\Livewire\Account\Profile;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_profile(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_opens_profile_from_simplified_layout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee('Abrir mi perfil', false)
            ->assertDontSee('Búsqueda global')
            ->assertDontSee('>Tema<', false);
    }

    public function test_user_updates_only_own_name_and_email_and_email_verification_is_reset(): void
    {
        $role = Role::query()->create(['slug' => 'viewer', 'name' => 'Consulta']);
        $user = User::factory()->create(['email' => 'old@example.test', 'status' => 'active', 'email_verified_at' => now()]);
        $other = User::factory()->create(['name' => 'Otra persona']);
        $user->roles()->attach($role);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', '  Nombre   Actualizado  ')
            ->set('email', 'NUEVO@EXAMPLE.TEST')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $user->refresh();
        $this->assertSame('Nombre Actualizado', $user->name);
        $this->assertSame('nuevo@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('active', $user->status);
        $this->assertTrue($user->hasRole('viewer'));
        $this->assertSame('Otra persona', $other->fresh()->name);
    }

    public function test_profile_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'used@example.test']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('email', $other->email)
            ->call('updateProfile')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_user_changes_password_with_current_password_and_audits_without_hash(): void
    {
        $user = User::factory()->create(['password' => 'Secret12345!']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'Secret12345!')
            ->set('password', 'NuevaClave12345!')
            ->set('password_confirmation', 'NuevaClave12345!')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertTrue(Hash::check('NuevaClave12345!', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'updated']);
        $logs = ActivityLog::query()->where('auditable_type', User::class)->where('auditable_id', $user->id)->get();
        $this->assertStringNotContainsString($user->fresh()->password, json_encode($logs->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_password_change_rejects_wrong_current_password_and_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'Secret12345!']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'Incorrecta123!')
            ->set('password', 'NuevaClave12345!')
            ->set('password_confirmation', 'OtraClave12345!')
            ->call('updatePassword')
            ->assertHasErrors(['current_password', 'password']);

        $this->assertTrue(Hash::check('Secret12345!', $user->fresh()->password));
    }
}
