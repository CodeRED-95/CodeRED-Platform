<?php

namespace Tests\Feature;

use App\Livewire\Account\ChangePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DesignSystemPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_pilot_renders_accessible_errors_and_loading_state(): void
    {
        $user = User::factory()->create([
            'password' => 'Secret12345!',
            'must_change_password' => true,
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->call('updatePassword')
            ->assertHasErrors(['current_password', 'password', 'password_confirmation'])
            ->assertSeeHtml('id="current-password-error"')
            ->assertSeeHtml('aria-describedby="current-password-error"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:target="updatePassword"')
            ->assertSee('Actualizando…');
    }
}
