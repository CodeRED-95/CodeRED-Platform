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
        $component->call('registerFile', 'private/ruc/incoming/padron.txt')->assertHasNoErrors();
        $import = RucImport::query()->sole();
        $component->call('startImport', $import->id)->assertHasNoErrors();
        Queue::assertPushedOn('ruc-imports', ProcessRucImportJob::class);
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
