<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Backups as AgencyBackups;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyRestoreStatus;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Jobs\RestoreAgencyBackupJob;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyBackup;
use App\Modules\Agencies\Models\AgencyBackupRestore;
use App\Modules\Agencies\Services\AgencyBackupService;
use App\Modules\Agencies\Services\AgencyRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    /**
     * Usuario con permisos concretos, para comprobar que restaurar exige el
     * permiso nuevo y no basta con ver copias.
     *
     * @param  array<int, string>  $slugs
     */
    private function userWithPermissions(array $slugs): User
    {
        $role = Role::query()->create(['slug' => 'backup-operator-'.uniqid(), 'name' => 'Operador de copias']);
        $ids = collect($slugs)->map(fn (string $slug): int => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug],
        )->id);
        $role->permissions()->sync($ids);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    /**
     * Agencia con todos los campos que el usuario pidió preservar.
     */
    private function fullyPopulatedAgency(array $overrides = []): Agency
    {
        return Agency::factory()->create(array_merge([
            'code' => 'AG-FULL-001',
            'name' => 'AGENCIA CENTRAL LIMA',
            'old_name' => 'AGENCIA ANTIGUA LIMA',
            'department' => 'LIMA',
            'province' => 'LIMA',
            'district' => 'MIRAFLORES',
            'address' => 'Av. Larco 123',
            'texto_chosen_terrestre' => 'CHOSEN-TERRESTRE-LIMA',
            'texto_chosen_aereo' => 'CHOSEN-AEREO-LIMA',
            'is_co' => true,
            'is_operations_center' => true,
            'status' => AgencyStatus::Active,
            'category' => 'GRANDE CO',
            'latitude' => -12.121212121212,
            'longitude' => -77.030303030303,
            'observations' => 'Observación importante de la agencia.',
            'move_notice' => 'Aviso de traslado visible al público.',
            'classification_category' => 'CLASIFICACION-A',
        ], $overrides));
    }

    public function test_backup_captures_every_agency_column_and_name_histories(): void
    {
        $this->superAdmin();
        $agency = $this->fullyPopulatedAgency();

        \DB::table('agency_name_histories')->insert([
            'agency_id' => $agency->id,
            'old_name' => 'AGENCIA ANTIGUA LIMA',
            'new_name' => 'AGENCIA CENTRAL LIMA',
            'source' => 'shalom_sync',
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $backup = app(AgencyBackupService::class)->create();

        $payload = json_decode(Storage::disk($backup->disk)->get($backup->path), true);

        $this->assertSame('agencies', $payload['metadata']['module']);
        $this->assertSame(AgencyBackupService::SCHEMA_VERSION, $payload['metadata']['schema_version']);

        $stored = collect($payload['data']['agencies'])->firstWhere('code', 'AG-FULL-001');
        $this->assertNotNull($stored);

        // Toda columna real de la tabla debe estar presente en el archivo.
        $columns = app(AgencyBackupService::class)->agencyColumns();
        foreach ($columns as $column) {
            $this->assertArrayHasKey($column, $stored, "Falta la columna {$column} en el respaldo.");
        }

        // Campos que el negocio exige conservar, comprobados uno a uno.
        $this->assertSame('AGENCIA ANTIGUA LIMA', $stored['old_name']);
        $this->assertSame('CHOSEN-TERRESTRE-LIMA', $stored['texto_chosen_terrestre']);
        $this->assertSame('CHOSEN-AEREO-LIMA', $stored['texto_chosen_aereo']);
        $this->assertTrue((bool) $stored['is_co']);
        $this->assertSame('Observación importante de la agencia.', $stored['observations']);

        $this->assertCount(1, $payload['data']['agency_name_histories']);
        $this->assertSame('AGENCIA ANTIGUA LIMA', $payload['data']['agency_name_histories'][0]['old_name']);
    }

    public function test_restore_recovers_every_field_including_move_and_old_name(): void
    {
        $this->superAdmin();

        $destination = Agency::factory()->create(['code' => 'AG-DEST-002', 'name' => 'AGENCIA DESTINO']);
        $moved = $this->fullyPopulatedAgency([
            'code' => 'AG-MOVED-003',
            'name' => 'AGENCIA TRASLADADA',
            'old_name' => 'AGENCIA TRASLADADA ANTIGUA',
            'has_moved' => true,
            'moved_to_agency_id' => $destination->id,
            'moved_to_address' => 'Nueva dirección tras el traslado 456',
            'move_notice' => 'Nos mudamos a la nueva sede.',
            'moved_at' => '2026-03-15',
        ]);

        $backup = app(AgencyBackupService::class)->create();

        // Se destruyen los datos como lo haría un incidente real.
        Agency::withoutEvents(function (): void {
            Agency::withTrashed()->forceDelete();
        });
        $this->assertSame(0, Agency::withTrashed()->count());

        $restore = AgencyBackupRestore::query()->create([
            'uuid' => (string) \Str::uuid(),
            'agency_backup_id' => $backup->id,
            'filename' => $backup->filename,
            'disk' => $backup->disk,
            'path' => $backup->path,
            'mode' => AgencyBackupRestore::MODE_MERGE,
            'status' => 'pending',
            'stage' => 'En cola',
        ]);

        app(AgencyRestoreService::class)->restore($restore);
        $restore->refresh();

        $this->assertSame(AgencyRestoreStatus::Completed, $restore->status);
        $this->assertSame(100, $restore->progress);

        $restored = Agency::withTrashed()->where('code', 'AG-MOVED-003')->firstOrFail();

        $this->assertSame('AGENCIA TRASLADADA', $restored->name);
        $this->assertSame('AGENCIA TRASLADADA ANTIGUA', $restored->old_name);
        $this->assertSame('LIMA', $restored->department);
        $this->assertSame('LIMA', $restored->province);
        $this->assertSame('MIRAFLORES', $restored->district);
        $this->assertSame('Av. Larco 123', $restored->address);
        $this->assertSame('CHOSEN-TERRESTRE-LIMA', $restored->texto_chosen_terrestre);
        $this->assertSame('CHOSEN-AEREO-LIMA', $restored->texto_chosen_aereo);
        $this->assertTrue((bool) $restored->getAttribute('is_co'));
        // El modelo marca como "moved" a las agencias trasladadas al guardar:
        // la restauración debe devolver ese estado real, no el original.
        $this->assertSame(AgencyStatus::Moved->value, $restored->status->value);
        $this->assertSame('GRANDE CO', $restored->category->value);
        $this->assertSame('Observación importante de la agencia.', $restored->observations);
        $this->assertSame('CLASIFICACION-A', $restored->classification_category);
        $this->assertNotNull($restored->latitude);
        $this->assertNotNull($restored->longitude);

        // Datos de traslado, incluida la agencia destino reenlazada.
        $this->assertTrue((bool) $restored->has_moved);
        $this->assertSame('Nueva dirección tras el traslado 456', $restored->moved_to_address);
        $this->assertSame('Nos mudamos a la nueva sede.', $restored->move_notice);
        $this->assertSame('2026-03-15', $restored->moved_at?->format('Y-m-d'));

        $restoredDestination = Agency::withTrashed()->where('code', 'AG-DEST-002')->firstOrFail();
        $this->assertSame($restoredDestination->id, $restored->moved_to_agency_id);

        $this->assertSame($moved->code, $restored->code);
    }

    public function test_restore_updates_existing_agency_matched_by_code_without_duplicating(): void
    {
        $this->superAdmin();
        $this->fullyPopulatedAgency(['code' => 'AG-SAME-004', 'name' => 'NOMBRE ORIGINAL']);

        $backup = app(AgencyBackupService::class)->create();

        // La agencia sigue existiendo pero alguien la editó por error.
        Agency::query()->where('code', 'AG-SAME-004')->update([
            'name' => 'NOMBRE PISADO POR ERROR',
            'old_name' => null,
            'texto_chosen_terrestre' => null,
        ]);

        $restore = $this->restoreFrom($backup);
        app(AgencyRestoreService::class)->restore($restore);

        $this->assertSame(1, Agency::withTrashed()->where('code', 'AG-SAME-004')->count());

        $agency = Agency::query()->where('code', 'AG-SAME-004')->firstOrFail();
        $this->assertSame('NOMBRE ORIGINAL', $agency->name);
        $this->assertSame('AGENCIA ANTIGUA LIMA', $agency->old_name);
        $this->assertSame('CHOSEN-TERRESTRE-LIMA', $agency->texto_chosen_terrestre);

        $restore->refresh();
        $this->assertSame(1, $restore->updated_records);
        $this->assertSame(0, $restore->created_records);
    }

    public function test_restore_creates_safety_backup_before_writing(): void
    {
        $this->superAdmin();
        $this->fullyPopulatedAgency(['code' => 'AG-SAFE-005']);

        $backup = app(AgencyBackupService::class)->create();
        $restore = $this->restoreFrom($backup);

        app(AgencyRestoreService::class)->restore($restore);
        $restore->refresh();

        $this->assertNotNull($restore->safety_backup_id);
        $safety = AgencyBackup::query()->findOrFail($restore->safety_backup_id);
        $this->assertStringContainsString('pre-restore', $safety->filename);
        $this->assertTrue(Storage::disk($safety->disk)->exists($safety->path));
    }

    public function test_merge_mode_never_deletes_agencies_absent_from_the_backup(): void
    {
        $this->superAdmin();
        $this->fullyPopulatedAgency(['code' => 'AG-IN-BACKUP-006']);

        $backup = app(AgencyBackupService::class)->create();

        // Creada después del respaldo: no está en el archivo.
        Agency::factory()->create(['code' => 'AG-NEW-007', 'name' => 'AGENCIA POSTERIOR']);

        $restore = $this->restoreFrom($backup, AgencyBackupRestore::MODE_MERGE);
        app(AgencyRestoreService::class)->restore($restore);

        $this->assertDatabaseHas('agencies', ['code' => 'AG-NEW-007', 'deleted_at' => null]);
        $this->assertSame(0, $restore->refresh()->trashed_records);
    }

    public function test_replace_mode_sends_missing_agencies_to_trash_without_hard_deleting(): void
    {
        $this->superAdmin();
        $this->fullyPopulatedAgency(['code' => 'AG-IN-BACKUP-008']);

        $backup = app(AgencyBackupService::class)->create();
        Agency::factory()->create(['code' => 'AG-NEW-009', 'name' => 'AGENCIA POSTERIOR']);

        $restore = $this->restoreFrom($backup, AgencyBackupRestore::MODE_REPLACE);
        app(AgencyRestoreService::class)->restore($restore);

        $this->assertSame(1, $restore->refresh()->trashed_records);

        // A papelera, nunca borrado definitivo: sigue siendo recuperable.
        $trashed = Agency::onlyTrashed()->where('code', 'AG-NEW-009')->first();
        $this->assertNotNull($trashed);
    }

    public function test_restore_rejects_a_file_that_is_not_an_agency_backup(): void
    {
        $this->superAdmin();
        Storage::disk('local')->put('backups/agencies/invalid.json', json_encode(['metadata' => ['module' => 'ruc'], 'data' => []]));

        $restore = AgencyBackupRestore::query()->create([
            'uuid' => (string) \Str::uuid(),
            'filename' => 'invalid.json',
            'disk' => 'local',
            'path' => 'backups/agencies/invalid.json',
            'mode' => AgencyBackupRestore::MODE_MERGE,
            'status' => 'pending',
            'stage' => 'En cola',
        ]);

        try {
            app(AgencyRestoreService::class)->restore($restore);
            $this->fail('Se esperaba un fallo al restaurar un archivo que no es copia de agencias.');
        } catch (\Throwable) {
            // esperado
        }

        $restore->refresh();
        $this->assertSame(AgencyRestoreStatus::Failed, $restore->status);
        $this->assertStringContainsString('no es una copia de seguridad de agencias', (string) $restore->error_message);
    }

    public function test_upload_queues_the_restore_instead_of_processing_in_the_request(): void
    {
        Queue::fake();
        $actor = $this->superAdmin();
        $this->fullyPopulatedAgency(['code' => 'AG-UPLOAD-010']);

        $backup = app(AgencyBackupService::class)->create();
        $contents = Storage::disk($backup->disk)->get($backup->path);

        Livewire::actingAs($actor)
            ->test(AgencyBackups::class)
            ->set('restoreFile', UploadedFile::fake()->createWithContent('copia-agencias.json', $contents))
            ->set('restoreMode', AgencyBackupRestore::MODE_MERGE)
            ->call('restoreFromUpload')
            ->assertHasNoErrors();

        // El trabajo pesado va a la cola: la petición HTTP no espera y no puede
        // agotar el tiempo de Cloudflare.
        Queue::assertPushed(RestoreAgencyBackupJob::class);

        $restore = AgencyBackupRestore::query()->latest('id')->firstOrFail();
        $this->assertSame('copia-agencias.json', $restore->filename);
        $this->assertSame(AgencyRestoreStatus::Pending, $restore->status);
        $this->assertGreaterThan(0, $restore->total_records);
    }

    public function test_upload_rejects_a_file_that_is_not_an_agency_backup(): void
    {
        Queue::fake();
        $actor = $this->superAdmin();

        Livewire::actingAs($actor)
            ->test(AgencyBackups::class)
            ->set('restoreFile', UploadedFile::fake()->createWithContent('otro.json', json_encode(['metadata' => ['module' => 'ruc']])))
            ->call('restoreFromUpload')
            ->assertHasErrors('restoreFile');

        Queue::assertNotPushed(RestoreAgencyBackupJob::class);
        $this->assertDatabaseCount('agency_backup_restores', 0);
    }

    public function test_restoring_requires_the_dedicated_permission(): void
    {
        Queue::fake();
        $viewer = $this->userWithPermissions(['agencies.view', 'agencies.backup.view']);
        $this->fullyPopulatedAgency(['code' => 'AG-PERM-011']);
        $backup = app(AgencyBackupService::class)->create();

        Livewire::actingAs($viewer)
            ->test(AgencyBackups::class)
            ->call('restoreFromBackup', $backup->id)
            ->assertForbidden();

        Queue::assertNotPushed(RestoreAgencyBackupJob::class);
    }

    public function test_download_returns_the_backup_file(): void
    {
        $actor = $this->superAdmin();
        $this->fullyPopulatedAgency(['code' => 'AG-DL-012']);
        $backup = app(AgencyBackupService::class)->create();

        $response = $this->actingAs($actor)->get(route('admin.agencies.backups.download', $backup));

        $response->assertOk();
        $this->assertStringContainsString('AG-DL-012', $response->streamedContent());
    }

    private function restoreFrom(AgencyBackup $backup, string $mode = AgencyBackupRestore::MODE_MERGE): AgencyBackupRestore
    {
        return AgencyBackupRestore::query()->create([
            'uuid' => (string) \Str::uuid(),
            'agency_backup_id' => $backup->id,
            'filename' => $backup->filename,
            'disk' => $backup->disk,
            'path' => $backup->path,
            'mode' => $mode,
            'status' => 'pending',
            'stage' => 'En cola',
        ]);
    }
}
