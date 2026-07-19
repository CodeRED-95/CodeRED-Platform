<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users\Form;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedUserPermissions(): void
    {
        collect([
            ['slug' => 'users.view', 'name' => 'Ver usuarios'],
            ['slug' => 'users.create', 'name' => 'Crear usuarios'],
            ['slug' => 'users.update', 'name' => 'Editar usuarios'],
            ['slug' => 'users.delete', 'name' => 'Eliminar usuarios'],
            ['slug' => 'users.restore', 'name' => 'Restaurar usuarios'],
            ['slug' => 'users.manage_roles', 'name' => 'Gestionar roles de usuarios'],
            ['slug' => 'users.reset_password', 'name' => 'Restablecer contraseñas'],
            ['slug' => 'users.manage_status', 'name' => 'Gestionar estado de usuarios'],
            ['slug' => 'users.view_activity', 'name' => 'Ver actividad de usuarios'],
        ])->each(fn (array $item) => Permission::query()->updateOrCreate(['slug' => $item['slug']], $item));

        $super = Role::query()->updateOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $editor = Role::query()->updateOrCreate(['slug' => 'editor'], ['name' => 'Editor', 'is_system' => false]);
        $viewer = Role::query()->updateOrCreate(['slug' => 'viewer'], ['name' => 'Consulta', 'is_system' => false]);

        $super->permissions()->sync(Permission::query()->pluck('id')->all());
        $editor->permissions()->sync([]);
        $viewer->permissions()->sync([]);
    }

    public function test_super_admin_can_open_users_index(): void
    {
        $this->seedUserPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        $this->actingAs($user)->get('/admin/users')->assertOk();
    }

    public function test_user_form_renders_livewire_submit_and_submit_button(): void
    {
        $this->seedUserPermissions();

        $actor = User::factory()->create(['status' => 'active']);
        $actor->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        $this->actingAs($actor);

        Livewire::test(Form::class)
            ->assertSeeHtml('wire:submit.prevent="save"')
            ->assertSeeHtml('<button type="submit"')
            ->assertSee('Guardar cambios')
            ->assertSee('Volver')
            ->assertDontSee('Guardar usuario')
            ->assertSee('Correo verificado')
            ->assertSee('Indica que la dirección de correo del usuario ya fue confirmada.')
            ->assertSee('Forzar cambio de contraseña')
            ->assertSee('El usuario deberá establecer una nueva contraseña en su próximo acceso.')
            ->assertSeeHtml('role="switch"')
            ->assertSeeHtml('aria-describedby="user-email-verified-description"')
            ->assertSeeHtml('aria-describedby="user-must-change-password-description"');
    }

    public function test_user_without_permission_gets_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_user_factory_can_generate_states(): void
    {
        $this->assertSame('suspended', User::factory()->suspended()->make()->status);
        $this->assertFalse(User::factory()->inactive()->make()->isActive());
        $this->assertTrue(User::factory()->mustChangePassword()->make()->must_change_password);
    }

    public function test_password_is_hashed_on_create(): void
    {
        $this->seedUserPermissions();
        $actor = User::factory()->create(['status' => 'active']);
        $actor->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        $this->actingAs($actor);

        Livewire::test(Form::class)
            ->set('name', 'Usuario Prueba')
            ->set('email', 'test@example.test')
            ->set('password', 'PasswordSeguro123!')
            ->set('password_confirmation', 'PasswordSeguro123!')
            ->set('roles', ['viewer'])
            ->set('status', 'active')
            ->call('save');

        $created = User::query()->where('email', 'test@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('PasswordSeguro123!', $created->password));
    }

    public function test_edit_user_persists_changes(): void
    {
        $this->seedUserPermissions();

        $actor = User::factory()->create(['status' => 'active']);
        $actor->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        $target = User::factory()->create([
            'name' => 'Usuario Original',
            'email' => 'original@example.test',
            'status' => 'active',
            'email_verified_at' => null,
        ]);
        $target->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'));

        $this->actingAs($actor);

        Livewire::test(Form::class, ['user' => $target->id])
            ->set('name', 'Usuario Editado')
            ->set('email', 'editado@example.test')
            ->set('roles', ['editor'])
            ->set('status', 'active')
            ->set('email_verified', true)
            ->set('must_change_password', true)
            ->call('save');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Usuario Editado',
            'email' => 'editado@example.test',
            'must_change_password' => true,
        ]);
        $target->refresh();
        $this->assertNotNull($target->email_verified_at);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $token = 'csrf-usuario-inactivo';
        $user = User::factory()->create([
            'status' => 'inactive',
            'is_active' => false,
            'password' => bcrypt('Secret12345!'),
        ]);

        $this->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'Secret12345!',
        ])->assertSessionHasErrors(['email']);
    }
}
