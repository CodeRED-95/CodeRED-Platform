<?php

namespace Tests\Feature;

use App\Livewire\Admin\Ruc\Imports;
use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Services\RucIncomingFileScanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RucImportUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Queue::fake();
        config()->set(['ruc.import.disk' => 'local', 'ruc.import.incoming_directory' => 'private/ruc/incoming', 'ruc.import.queue' => 'ruc-imports']);
    }

    public function test_scanner_creates_directory_detects_only_txt_and_ignores_archive(): void
    {
        $scanner = app(RucIncomingFileScanner::class);
        $this->assertSame([], $scanner->scan());
        Storage::disk('local')->assertExists('private/ruc/incoming');
        Storage::disk('local')->put('private/ruc/incoming/padron SUNAT.txt', 'RUC|RAZON SOCIAL|');
        Storage::disk('local')->put('private/ruc/incoming/padron.zip', 'zip');
        Storage::disk('local')->put('private/ruc/archive/anterior.txt', 'old');
        $files = $scanner->scan();
        $this->assertCount(1, $files);
        $file = collect($files)->first();
        $this->assertIsArray($file);
        $this->assertSame('padron SUNAT.txt', $file['name']);
    }

    public function test_detect_register_and_start_server_file_from_livewire(): void
    {
        $user = $this->roleUser('super-admin');
        $component = Livewire::actingAs($user)->test(Imports::class)->assertSet('availableFiles', []);
        Storage::disk('local')->put('private/ruc/incoming/padron.txt', "RUC|RAZON SOCIAL|\n20512805478|EMPRESA|");
        $component->call('scanFiles')->assertSet('availableFiles.0.name', 'padron.txt');
        $component->assertDontSee('@js(', false)
            ->assertSeeHtml('wire:click="validateIncomingFile(&quot;private/ruc/incoming/padron.txt&quot;)"')
            ->assertSeeHtml('wire:click="registerIncomingFile(&quot;private/ruc/incoming/padron.txt&quot;)"');
        $component->call('registerIncomingFile', 'private/ruc/incoming/padron.txt')->assertHasNoErrors();
        $import = RucImport::query()->sole();
        $component->call('startImport', $import->id)->assertHasNoErrors();
        Queue::assertPushedOn('ruc-imports', ProcessRucImportJob::class);
    }

    public function test_super_admin_can_validate_and_sees_the_sample_result(): void
    {
        Storage::disk('local')->put('private/ruc/incoming/padrón con espacios.txt', "RUC|NOMBRE O RAZÓN SOCIAL|ESTADO DEL CONTRIBUYENTE|CONDICIÓN DE DOMICILIO|UBIGEO|\r\n20512805478|EMPRESA DE PRUEBA|ACTIVO|HABIDO|150140|\r\n");

        Livewire::actingAs($this->roleUser('super-admin'))
            ->test(Imports::class)
            ->call('validateIncomingFile', 'private/ruc/incoming/padrón con espacios.txt')
            ->assertHasNoErrors()
            ->assertSee('Archivo válido')
            ->assertSee('UTF-8');
    }

    public function test_rejects_missing_unsafe_non_txt_and_invalid_files(): void
    {
        $user = $this->roleUser('super-admin');
        Storage::disk('local')->put('private/ruc/incoming/invalido.txt', "CABECERA|INCORRECTA|\nvalor|invalido|");
        Storage::disk('local')->put('private/ruc/incoming/padron.csv', 'RUC,NOMBRE');

        Livewire::actingAs($user)->test(Imports::class)
            ->call('validateIncomingFile', '../archive/padron.txt')->assertHasErrors('incomingFiles')
            ->call('validateIncomingFile', 'private/ruc/incoming/inexistente.txt')->assertHasErrors('incomingFiles')
            ->call('validateIncomingFile', 'private/ruc/incoming/padron.csv')->assertHasErrors('incomingFiles')
            ->call('validateIncomingFile', 'private/ruc/incoming/invalido.txt')->assertHasErrors('incomingFiles');
    }

    public function test_empty_file_and_duplicate_registration_are_reported(): void
    {
        $user = $this->roleUser('super-admin');
        Storage::disk('local')->put('private/ruc/incoming/vacio.txt', '');
        Livewire::actingAs($user)->test(Imports::class)
            ->call('validateIncomingFile', 'private/ruc/incoming/vacio.txt')
            ->assertHasErrors('incomingFiles');

        Storage::disk('local')->put('private/ruc/incoming/padron.txt', "RUC|RAZON SOCIAL|\n20512805478|EMPRESA|");
        Livewire::actingAs($user)->test(Imports::class)
            ->call('registerIncomingFile', 'private/ruc/incoming/padron.txt')
            ->assertHasNoErrors()
            ->call('registerIncomingFile', 'private/ruc/incoming/padron.txt')
            ->assertHasErrors('incomingFiles');
        $this->assertDatabaseCount('ruc_imports', 1);
    }

    public function test_users_without_ruc_permissions_receive_forbidden(): void
    {
        foreach (['editor', 'viewer'] as $role) {
            Livewire::actingAs($this->roleUser($role))
                ->test(Imports::class)
                ->assertForbidden();
        }
    }

    public function test_large_file_is_scanned_from_metadata_without_contents(): void
    {
        Storage::disk('local')->put('private/ruc/incoming/grande.txt', str_repeat('1234567890', 200_000));
        $file = collect(app(RucIncomingFileScanner::class)->scan())->first();
        $this->assertIsArray($file);
        $this->assertSame(2_000_000, $file['size']);
        $this->assertArrayNotHasKey('contents', $file);
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
