<?php

declare(strict_types=1);

namespace Tests\Feature\ShalomRecordar;

use App\Livewire\Admin\ShalomRecordar\InstallationShow;
use App\Models\Role;
use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/**
 * Gestión de lotes de Shalom Recordar: ver, exportar, eliminar, filtro por
 * fecha y seguridad por propiedad. No dispara integraciones externas.
 */
class RecordarBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function installationFor(User $user): ShalomRecordarInstallation
    {
        return ShalomRecordarInstallation::query()->create([
            'user_id' => $user->id,
            'installation_uuid' => (string) Str::uuid(),
            'extension_version' => '2.7.0',
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Crea un registro. `$batch` va a sync_batch_id salvo que sea null, para
     * poder probar el lote heredado (sin sync_batch_id) que usa el fallback.
     */
    private function record(ShalomRecordarInstallation $inst, string $field, string $value, ?string $batch, ?string $when = null): ShalomRecordarRecord
    {
        $at = $when ? Carbon::parse($when) : now();

        return ShalomRecordarRecord::query()->create([
            'user_id' => $inst->user_id,
            'installation_id' => $inst->id,
            'installation_uuid' => $inst->installation_uuid,
            'record_hash' => hash('sha256', $field.$value.$at.mt_rand()),
            'field' => $field,
            'value' => $value,
            'recorded_at' => $at,
            'sync_batch_id' => $batch,
            'sync_cursor' => $at->toISOString(),
        ]);
    }

    public function test_deleting_a_batch_removes_only_its_records_and_updates_counters(): void
    {
        $admin = $this->admin();
        $inst = $this->installationFor($admin);
        $this->record($inst, 'DNI', '11111111', 'batch-A');
        $this->record($inst, 'DNI', '22222222', 'batch-A');
        $this->record($inst, 'DNI', '33333333', 'batch-B');

        Livewire::actingAs($admin)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->call('deleteSyncBatch', base64_encode('batch-A'))
            ->assertHasNoErrors();

        // Solo se borró el lote A.
        $this->assertSame(0, ShalomRecordarRecord::query()->where('installation_id', $inst->id)->where('sync_batch_id', 'batch-A')->count());
        $this->assertSame(1, ShalomRecordarRecord::query()->where('installation_id', $inst->id)->count());
    }

    /** El bug original: un lote sin sync_batch_id (clave por fallback) no se podía borrar. */
    public function test_deleting_a_legacy_batch_without_sync_batch_id_works(): void
    {
        $admin = $this->admin();
        $inst = $this->installationFor($admin);
        // sync_batch_id null: la clave del lote es el sync_cursor.
        $legacy = $this->record($inst, 'DNI', '44444444', null, '2026-08-10 10:00:00');
        $key = $legacy->sync_cursor;

        Livewire::actingAs($admin)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->call('deleteSyncBatch', base64_encode((string) $key))
            ->assertHasNoErrors();

        $this->assertSame(0, ShalomRecordarRecord::query()->where('installation_id', $inst->id)->count());
    }

    public function test_view_batch_shows_only_that_batch_records(): void
    {
        $admin = $this->admin();
        $inst = $this->installationFor($admin);
        $this->record($inst, 'DNI', 'AAA-11111111', 'batch-A');
        $this->record($inst, 'RUC', 'BBB-20123456789', 'batch-B');

        Livewire::actingAs($admin)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->call('viewBatch', base64_encode('batch-A'))
            ->assertSee('AAA-11111111')
            ->assertDontSee('BBB-20123456789');
    }

    public function test_batch_detail_search_filters_by_field_or_value(): void
    {
        $admin = $this->admin();
        $inst = $this->installationFor($admin);
        $this->record($inst, 'DNI', 'BUSCA-ESTO', 'batch-A');
        $this->record($inst, 'OS', 'OTRO-VALOR', 'batch-A');

        Livewire::actingAs($admin)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->call('viewBatch', base64_encode('batch-A'))
            ->set('search', 'BUSCA')
            ->assertSee('BUSCA-ESTO')
            ->assertDontSee('OTRO-VALOR');
    }

    public function test_date_filter_limits_batches_and_can_be_cleared(): void
    {
        $admin = $this->admin();
        $inst = $this->installationFor($admin);
        $this->record($inst, 'DNI', 'HOY', 'batch-hoy', '2026-08-10 09:00:00');
        $this->record($inst, 'DNI', 'AYER', 'batch-ayer', '2026-08-09 09:00:00');

        Livewire::actingAs($admin)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->set('date', '2026-08-10')
            ->assertViewHas('batches', fn ($batches) => $batches->count() === 1 && $batches->first()->batch_id === 'batch-hoy')
            ->call('clearDate')
            ->assertViewHas('batches', fn ($batches) => $batches->count() === 2);
    }

    public function test_export_batch_returns_a_valid_xlsx_with_context_and_records(): void
    {
        $admin = $this->admin();
        $inst = $this->installationFor($admin);
        $this->record($inst, 'DNI', '12345678', 'batch-X');
        $this->record($inst, 'RUC', '20123456789', 'batch-X');

        // La acción de Livewire devuelve una descarga; el contenido del .xlsx
        // se valida ejecutando el servicio directamente.
        Livewire::actingAs($admin)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->call('exportBatch', base64_encode('batch-X'))
            ->assertHasNoErrors();

        $export = app(ShalomRecordarSyncService::class)->exportBatchToXlsx($inst, 'batch-X');
        $this->assertStringEndsWith('.xlsx', $export['filename']);
        $this->assertSame(2, $export['records_count']);

        // Es un ZIP OOXML válido y contiene la hoja y los valores exportados.
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export['path']) === true);
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($export['path']);

        $this->assertStringContainsString($inst->installation_uuid, $sheet);
        $this->assertStringContainsString('12345678', $sheet);
        $this->assertStringContainsString('20123456789', $sheet);
        $this->assertStringContainsString('Fecha', $sheet);
    }

    public function test_a_user_cannot_manage_batches_of_another_user(): void
    {
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('slug', 'viewer')->firstOrFail());
        $inst = $this->installationFor($owner);
        $this->record($inst, 'DNI', '99999999', 'batch-A');

        $intruder = User::factory()->create();
        $intruder->roles()->attach(Role::query()->where('slug', 'viewer')->firstOrFail());

        // Ni siquiera puede montar la vista de una instalación ajena.
        Livewire::actingAs($intruder)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->assertForbidden();
    }

    public function test_owner_viewer_can_manage_their_own_batches(): void
    {
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('slug', 'viewer')->firstOrFail());
        $inst = $this->installationFor($owner);
        $this->record($inst, 'DNI', '12312312', 'batch-A');

        Livewire::actingAs($owner)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->assertOk()
            ->call('deleteSyncBatch', base64_encode('batch-A'))
            ->assertHasNoErrors();

        $this->assertSame(0, ShalomRecordarRecord::query()->where('installation_id', $inst->id)->count());
    }

    public function test_the_general_records_list_below_batches_is_gone(): void
    {
        $admin = $this->admin();
        $inst = $this->installationFor($admin);
        $this->record($inst, 'DNI', 'SOLO-EN-DETALLE', 'batch-A');

        // Sin abrir un lote, el valor no aparece: ya no hay lista general.
        Livewire::actingAs($admin)
            ->test(InstallationShow::class, ['installation' => $inst])
            ->assertDontSee('SOLO-EN-DETALLE')
            ->assertSee('Lotes de sincronización');
    }
}
