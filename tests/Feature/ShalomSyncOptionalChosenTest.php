<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\ShalomSync;
use App\Livewire\Admin\Agencies\ShalomSyncRun;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Jobs\SyncShalomAgenciesJob;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El archivo Chosen dejo de ser obligatorio: el extractor obtiene las agencias
 * de shalom.com.pe por su cuenta y ese archivo solo aporta los textos
 * texto_chosen_*.
 */
class ShalomSyncOptionalChosenTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_sincronizacion_arranca_sin_archivo_chosen(): void
    {
        Bus::fake();
        Storage::fake('local');

        Livewire::actingAs($this->admin())
            ->test(ShalomSync::class)
            ->call('sync')
            ->assertHasNoErrors()
            ->assertRedirect();

        $run = AgencyImportRun::query()->firstOrFail();

        $this->assertNull($run->chosen_storage_path);
        $this->assertNull($run->chosen_original_name);

        Bus::assertDispatched(SyncShalomAgenciesJob::class, function (SyncShalomAgenciesJob $job) use ($run): bool {
            return $job->importRunId === $run->id && $job->chosenPath === null;
        });
    }

    public function test_si_se_sube_el_chosen_se_conserva_y_viaja_al_job(): void
    {
        Bus::fake();
        Storage::fake('local');

        Livewire::actingAs($this->admin())
            ->test(ShalomSync::class)
            ->set('chosenFile', UploadedFile::fake()->createWithContent('chosen.json', '[]'))
            ->call('sync')
            ->assertHasNoErrors()
            ->assertRedirect();

        $run = AgencyImportRun::query()->firstOrFail();

        $this->assertSame('chosen.json', $run->chosen_original_name);
        $this->assertNotNull($run->chosen_storage_path);
        Storage::assertExists($run->chosen_storage_path);

        Bus::assertDispatched(SyncShalomAgenciesJob::class, function (SyncShalomAgenciesJob $job) use ($run): bool {
            return $job->chosenPath === $run->chosen_storage_path;
        });
    }

    public function test_una_ejecucion_sin_chosen_se_puede_reintentar(): void
    {
        Bus::fake();
        $run = AgencyImportRun::create([
            'type' => 'shalom_sync',
            'status' => 'failed',
            'error_message' => 'Anterior',
            'chosen_storage_path' => null,
        ]);

        $component = new ShalomSyncRun;
        $component->importRun = $run;

        $this->actingAs($this->admin());
        $component->retry();

        Bus::assertDispatched(SyncShalomAgenciesJob::class, fn (SyncShalomAgenciesJob $job): bool => $job->chosenPath === null);
        $this->assertSame('pending', $run->fresh()->status);
    }

    public function test_el_job_no_envia_chosen_al_extractor_cuando_no_hay_archivo(): void
    {
        $job = new SyncShalomAgenciesJob(1);

        $this->assertNull($job->chosenPath);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador']);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }
}
