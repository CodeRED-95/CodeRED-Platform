<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Import;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Services\AgencyBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyImportWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_cannot_run_before_validation_and_confirmation(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();

        Livewire::actingAs($actor)->test(Import::class)
            ->call('import')
            ->assertHasErrors(['source'])
            ->assertSet('step', 1);

        $this->assertDatabaseCount('agency_imports', 0);
    }

    public function test_wizard_detects_duplicates_and_imports_exact_validated_snapshot(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        Agency::factory()->create([
            'code' => 'SHA-000001',
            'source' => 'github_gist',
            'source_reference' => '1',
        ]);
        $payload = [
            $this->row(1, 'Agencia duplicada'),
            $this->row(2, 'Agencia nueva'),
            ['id' => 3],
        ];

        $component = Livewire::actingAs($actor)->test(Import::class)
            ->set('sourceType', 'json')
            ->set('jsonPayload', json_encode($payload, JSON_THROW_ON_ERROR))
            ->call('goToValidation')
            ->assertSet('step', 2)
            ->call('validateAndPreview')
            ->assertSet('step', 3)
            ->assertSet('summary.total_rows', 3)
            ->assertSet('summary.valid_rows', 2)
            ->assertSet('summary.invalid_rows', 1)
            ->assertSet('summary.duplicate_rows', 1)
            ->assertSee('Agencia duplicada')
            ->assertSee('Duplicada');

        $snapshotPath = $component->get('snapshotPath');
        $this->assertIsString($snapshotPath);
        Storage::disk('local')->assertExists($snapshotPath);

        $component
            ->set('jsonPayload', json_encode([...$payload, $this->row(999, 'No debe importarse')], JSON_THROW_ON_ERROR))
            ->call('goToImport')
            ->assertSet('step', 4)
            ->call('import')
            ->assertSet('step', 4)
            ->assertHasErrors(['import']);

        $this->assertDatabaseMissing('agencies', ['source_reference' => '2']);
        $this->assertDatabaseMissing('agencies', ['source_reference' => '999']);
        $this->assertDatabaseCount('agency_imports', 0);
    }

    public function test_generated_backup_can_be_previewed_and_restored_without_editing_json(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        Agency::factory()->count(3)->create();
        $backup = app(AgencyBackupService::class)->create($actor->id, 'local', null, false);
        $contents = Storage::disk('local')->get($backup->path);
        Agency::query()->forceDelete();

        Livewire::actingAs($actor)->test(Import::class)
            ->set('sourceType', 'json')
            ->set('jsonPayload', $contents)
            ->call('goToValidation')
            ->call('validateAndPreview')
            ->assertSet('step', 3)
            ->assertSet('summary.total_rows', 3)
            ->assertSet('payloadMetadata.format', 'data.agencies')
            ->call('goToImport')
            ->call('import')
            ->assertSet('step', 5);

        $this->assertDatabaseCount('agencies', 3);
    }

    public function test_file_required_message_is_translated(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(Import::class)
            ->set('sourceType', 'file')
            ->call('goToValidation')
            ->assertHasErrors(['file'])
            ->assertSee('Selecciona una copia de seguridad JSON para continuar.');

        $this->assertStringNotContainsString('validation.required_if', $component->html());
    }

    private function row(int $id, string $name): array
    {
        return [
            'id' => $id,
            'agencia' => $name,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Miraflores',
            'direccion' => 'Dirección '.$id,
            'tamano' => 'Mediano',
            'co' => false,
        ];
    }

    private function superAdmin(): User
    {
        $role = Role::query()->create([
            'name' => 'Super Administrador',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
