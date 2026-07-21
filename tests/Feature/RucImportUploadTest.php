<?php

namespace Tests\Feature;

use App\Livewire\Admin\Ruc\Imports;
use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Models\RucImport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        config()->set('ruc.import_disk', 'local');
        config()->set('ruc.import_directory', 'ruc-imports');
        config()->set('ruc.import_queue', 'ruc-imports');
        config()->set('ruc.import_max_size_mb', 5);
    }

    public function test_selected_txt_is_persisted_and_dispatched_to_ruc_queue(): void
    {
        $contents = file_get_contents(base_path('tests/Fixtures/ruc/sunat-real-format.txt'));
        $user = $this->roleUser('super-admin');

        Livewire::actingAs($user)->test(Imports::class)
            ->set('file', UploadedFile::fake()->createWithContent('padron.txt', $contents))
            ->call('start')
            ->assertHasNoErrors()
            ->assertSet('file', null);

        $import = RucImport::query()->sole();
        Storage::disk('local')->assertExists($import->path);
        $this->assertSame('ruc-imports', $import->queue_name);
        Queue::assertPushedOn('ruc-imports', ProcessRucImportJob::class);
    }

    public function test_missing_empty_and_wrong_extension_are_reported_in_spanish(): void
    {
        $component = Livewire::actingAs($this->roleUser('super-admin'))->test(Imports::class);
        $component->call('start')->assertHasErrors(['file'])->assertSee('Selecciona un archivo TXT.');

        Livewire::actingAs($this->roleUser('super-admin'))->test(Imports::class)
            ->set('file', UploadedFile::fake()->createWithContent('padron.csv', ''))
            ->call('start')->assertHasErrors(['file']);
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
