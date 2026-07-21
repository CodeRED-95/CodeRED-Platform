<?php

namespace Tests\Feature;

use App\Livewire\Admin\Reniec\Imports;
use App\Models\Role;
use App\Models\User;
use App\Modules\Reniec\Services\ReniecIncomingFileScanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReniecIncomingFileScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set([
            'reniec.import.disk' => 'local',
            'reniec.import.incoming_directory' => 'private/reniec/incoming',
            'reniec.import.archive_directory' => 'private/reniec/archive',
        ]);
    }

    public function test_creates_missing_directory_and_reports_diagnostics(): void
    {
        $scanner = app(ReniecIncomingFileScanner::class);

        $this->assertFalse(Storage::disk('local')->exists('private/reniec/incoming'));

        $diagnostics = $scanner->diagnostics();

        Storage::disk('local')->assertExists('private/reniec/incoming');
        $this->assertTrue($diagnostics['exists']);
        $this->assertTrue($diagnostics['readable']);
        $this->assertSame('local', $diagnostics['disk']);
        $this->assertSame('private/reniec/incoming', $diagnostics['configured_directory']);
    }

    public function test_detects_only_txt_files_in_incoming_including_names_with_spaces(): void
    {
        Storage::disk('local')->put('private/reniec/incoming/padron RENIEC 2026.TXT', '12345678|ANA');
        Storage::disk('local')->put('private/reniec/incoming/manual.pdf', 'pdf');
        Storage::disk('local')->put('private/reniec/incoming/lote.zip', 'zip');
        Storage::disk('local')->put('private/reniec/archive/antiguo.txt', 'archivado');

        $files = app(ReniecIncomingFileScanner::class)->scan();

        $this->assertCount(1, $files);
        $this->assertSame('padron RENIEC 2026.TXT', $files[0]['name']);
        $this->assertSame('no_registrado', $files[0]['status']);
    }

    public function test_scans_large_file_using_only_metadata(): void
    {
        $contents = str_repeat('0123456789', 200_000);
        Storage::disk('local')->put('private/reniec/incoming/grande.txt', $contents);
        unset($contents);

        $files = app(ReniecIncomingFileScanner::class)->scan();

        $this->assertSame(2_000_000, $files[0]['size']);
        $this->assertArrayNotHasKey('contents', $files[0]);
    }

    public function test_detect_files_button_refreshes_livewire_list(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());
        $component = Livewire::actingAs($user)->test(Imports::class)
            ->assertSet('availableFiles', []);

        Storage::disk('local')->put('private/reniec/incoming/nuevo archivo.txt', '12345678|ANA');

        $component->call('scanFiles')
            ->assertSet('availableFiles.0.name', 'nuevo archivo.txt')
            ->assertSet('diagnostics.txt_count', 1);
    }
}
