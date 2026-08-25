<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\ShalomSync;
use App\Livewire\Admin\Agencies\ShalomSyncRun;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Actions\UpdateAgencyNameAction;
use App\Modules\Agencies\Jobs\SyncShalomAgenciesJob;
use App\Modules\Agencies\Services\ChosenFileParser;
use App\Services\Agencies\ShalomAgencyNormalizer;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
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

    public function test_sin_chosen_la_sincronizacion_no_propone_tocar_los_textos_chosen(): void
    {
        // El normalizador fabrica siempre un texto Chosen a partir de los datos
        // de la agencia ("28 - CAJAMARCA - JAEN - JAEN - JAEN CO - TERRESTRE"),
        // pero el valor real de Shalom no siempre tiene esa forma. Sin archivo
        // no hay fuente fiable, asi que no debe viajar en la propuesta.
        config(['services.shalom_extractor.enabled' => true]);

        Http::fake([
            '*/extract' => Http::response(['agencies' => [[
                'external_id' => 28,
                'code' => 'JAENCO',
                'name' => 'JAEN CO',
                'department' => 'CAJAMARCA',
                'province' => 'JAEN',
                'district' => 'JAEN',
                'address' => 'AV SIEMPRE VIVA 123',
            ]]], 200),
        ]);

        $run = AgencyImportRun::create(['type' => 'shalom_sync', 'status' => 'pending']);

        (new SyncShalomAgenciesJob($run->id))->handle(
            app(ChosenFileParser::class),
            app(ShalomAgencyNormalizer::class),
            app(UpdateAgencyNameAction::class),
        );

        $item = $run->items()->firstOrFail();

        // Ni el valor fabricado ni ningun otro: el campo viaja vacio, de modo
        // que la comparacion lo ignora y la escritura lo respeta.
        $this->assertNull($item->incoming_data['texto_chosen_terrestre'] ?? null);
        $this->assertNull($item->incoming_data['texto_chosen_aereo'] ?? null);
        // El resto de la agencia si viaja con normalidad.
        $this->assertSame('JAEN CO', $item->incoming_data['name']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador']);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }
}
