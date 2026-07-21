<?php

namespace Tests\Feature;

use App\Livewire\Admin\Reniec\Imports;
use App\Models\Role;
use App\Models\User;
use App\Modules\Reniec\Enums\ReniecImportStatus;
use App\Modules\Reniec\Jobs\ProcessReniecImportJob;
use App\Modules\Reniec\Services\ReniecCopyLoader;
use App\Modules\Reniec\Services\ReniecFileService;
use App\Modules\Reniec\Services\ReniecMergeService;
use App\Modules\Reniec\Support\ReniecLineParser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReniecImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        config()->set(['reniec.disk' => 'local', 'reniec.incoming_directory' => 'private/reniec/incoming', 'reniec.chunk_size' => 2, 'reniec.encoding' => 'UTF-8', 'reniec.delimiter' => '|', 'reniec.validate_checksum' => true]);
    }

    public function test_registers_server_file_and_rejects_path_traversal_and_low_space_contract(): void
    {
        Storage::disk('local')->put('private/reniec/incoming/padron.txt', "DNI|NOMBRES|PATERNO|MATERNO|\n12345678|ANA|PEREZ|DIAZ|\n");
        $import = app(ReniecFileService::class)->register('private/reniec/incoming/padron.txt');
        $this->assertSame(ReniecImportStatus::Registered, $import->status);
        $this->assertSame(64, strlen($import->file_hash));
        $this->expectException(ValidationException::class);
        app(ReniecFileService::class)->register('../padron.txt');
    }

    public function test_streams_checkpoints_merges_and_ignores_duplicates(): void
    {
        Storage::disk('local')->put('private/reniec/incoming/padron.txt', "DNI|NOMBRES|PATERNO|MATERNO|FECHA|SEXO|UBIGEO|\n12345678|ANA|PEREZ|DIAZ|1990-01-01|F|150101|\n12345678|ANA|PEREZ|DIAZ|1990-01-01|F|150101|\nBAD|X|Y|Z||||\n");
        $import = app(ReniecFileService::class)->register('private/reniec/incoming/padron.txt');
        (new ProcessReniecImportJob($import->id))->handle(app(ReniecFileService::class), app(ReniecLineParser::class), app(ReniecCopyLoader::class), app(ReniecMergeService::class));
        $import->refresh();
        $this->assertSame(ReniecImportStatus::CompletedWithErrors, $import->status);
        $this->assertGreaterThan(0, $import->current_byte_offset);
        $this->assertSame(1, $import->invalid_rows);
        $this->assertDatabaseCount('dni_records', 1);
        $this->assertDatabaseHas('dni_records', ['dni' => '12345678', 'ubigeo' => '150101']);
    }

    public function test_only_super_admin_opens_panel_and_start_uses_exclusive_queue(): void
    {
        $super = $this->user('super-admin');
        $editor = $this->user('editor');
        $this->actingAs($super)->get('/admin/reniec/importaciones')->assertOk();
        $this->actingAs($editor)->get('/admin/reniec/importaciones')->assertForbidden();
        Queue::fake();
        Storage::disk('local')->put('private/reniec/incoming/padron.txt', "DNI|NOMBRES|PATERNO|MATERNO|\n12345678|ANA|PEREZ|DIAZ|\n");
        Livewire::actingAs($super)->test(Imports::class)->set('selectedPath', 'private/reniec/incoming/padron.txt')->call('registerAndStart')->assertHasNoErrors();
        Queue::assertPushedOn('reniec-imports', ProcessReniecImportJob::class);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $u;
    }
}
