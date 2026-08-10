<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyRestoreStatus;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyBackup;
use App\Modules\Agencies\Models\AgencyBackupRestore;
use App\Modules\Agencies\Services\AgencyBackupService;
use App\Modules\Agencies\Services\AgencyRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Restauración portable entre instalaciones: los ids de usuario/ubigeo del
 * archivo pueden no existir en el destino y no deben provocar un fallo de FK.
 *
 * No dispara integraciones externas (tokens, webhooks, n8n, Telegram).
 */
class AgencyRestoreForeignKeysTest extends TestCase
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
     * Reescribe el archivo de backup aplicando una transformación a cada
     * agencia, simulando una copia hecha en otra instalación.
     *
     * @param  callable(array<string,mixed>):array<string,mixed>  $mutator
     */
    private function mutateBackupAgencies(AgencyBackup $backup, callable $mutator): void
    {
        $disk = Storage::disk($backup->disk);
        $payload = json_decode($disk->get($backup->path), true);

        $payload['data']['agencies'] = array_map($mutator, $payload['data']['agencies']);

        $disk->put($backup->path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function restoreFrom(AgencyBackup $backup, ?int $actorId): AgencyBackupRestore
    {
        return AgencyBackupRestore::query()->create([
            'uuid' => (string) Str::uuid(),
            'agency_backup_id' => $backup->id,
            'filename' => $backup->filename,
            'disk' => $backup->disk,
            'path' => $backup->path,
            'mode' => AgencyBackupRestore::MODE_MERGE,
            'status' => 'pending',
            'stage' => 'En cola',
            'created_by' => $actorId,
        ]);
    }

    private function makeAgency(array $overrides = []): Agency
    {
        return Agency::factory()->create(array_merge([
            'code' => 'AG-FK-001',
            'name' => 'AGENCIA FK',
            'old_name' => 'AGENCIA FK ANTIGUA',
            'texto_chosen_terrestre' => 'CHOSEN-T',
            'texto_chosen_aereo' => 'CHOSEN-A',
            'category' => 'GRANDE CO',
            'observations' => 'obs',
        ], $overrides));
    }

    public function test_restore_preserves_created_by_when_the_user_exists(): void
    {
        $admin = $this->superAdmin();
        $author = User::factory()->create();

        $this->makeAgency(['code' => 'AG-KEEP-001']);
        DB::table('agencies')->where('code', 'AG-KEEP-001')->update(['created_by' => $author->id, 'updated_by' => $author->id]);

        $backup = app(AgencyBackupService::class)->create();

        // El autor sigue existiendo; se destruyen solo las agencias.
        Agency::withoutEvents(fn () => Agency::withTrashed()->forceDelete());

        app(AgencyRestoreService::class)->restore($this->restoreFrom($backup, $admin->id));

        $agency = Agency::query()->where('code', 'AG-KEEP-001')->firstOrFail();
        $this->assertSame($author->id, $agency->created_by);
        $this->assertSame($author->id, $agency->updated_by);
    }

    public function test_restore_falls_back_to_the_running_admin_when_created_by_is_missing(): void
    {
        $admin = $this->superAdmin();
        $this->makeAgency(['code' => 'AG-MISS-002']);

        $backup = app(AgencyBackupService::class)->create();

        // La copia trae ids de usuario que NO existen en el destino.
        $this->mutateBackupAgencies($backup, function (array $agency): array {
            $agency['created_by'] = 999001;
            $agency['updated_by'] = 999002;

            return $agency;
        });

        Agency::withoutEvents(fn () => Agency::withTrashed()->forceDelete());

        $restore = $this->restoreFrom($backup, $admin->id);
        app(AgencyRestoreService::class)->restore($restore);

        $restore->refresh();
        $this->assertSame(AgencyRestoreStatus::Completed, $restore->status);

        $agency = Agency::query()->where('code', 'AG-MISS-002')->firstOrFail();
        // Se sustituyen por el admin que ejecuta la restauración.
        $this->assertSame($admin->id, $agency->created_by);
        $this->assertSame($admin->id, $agency->updated_by);
    }

    public function test_restore_nulls_missing_user_fk_when_there_is_no_running_admin(): void
    {
        $this->superAdmin();
        $this->makeAgency(['code' => 'AG-NULL-003']);

        $backup = app(AgencyBackupService::class)->create();
        $this->mutateBackupAgencies($backup, function (array $agency): array {
            $agency['created_by'] = 999001;
            $agency['updated_by'] = 999002;

            return $agency;
        });

        Agency::withoutEvents(fn () => Agency::withTrashed()->forceDelete());

        // Sin actor válido (created_by de la restauración = null): no hay a quién
        // asignar, así que la FK queda en null en vez de romper.
        $restore = $this->restoreFrom($backup, null);
        app(AgencyRestoreService::class)->restore($restore);

        $restore->refresh();
        $this->assertSame(AgencyRestoreStatus::Completed, $restore->status);

        $agency = Agency::query()->where('code', 'AG-NULL-003')->firstOrFail();
        $this->assertNull($agency->created_by);
        $this->assertNull($agency->updated_by);
    }

    public function test_restore_nulls_a_missing_ubigeo_id(): void
    {
        $admin = $this->superAdmin();
        $this->makeAgency(['code' => 'AG-UBI-004']);

        $backup = app(AgencyBackupService::class)->create();
        $this->mutateBackupAgencies($backup, function (array $agency): array {
            $agency['ubigeo_id'] = 424242; // no existe en el destino

            return $agency;
        });

        Agency::withoutEvents(fn () => Agency::withTrashed()->forceDelete());

        $restore = $this->restoreFrom($backup, $admin->id);
        app(AgencyRestoreService::class)->restore($restore);

        $this->assertSame(AgencyRestoreStatus::Completed, $restore->refresh()->status);
        $this->assertNull(Agency::query()->where('code', 'AG-UBI-004')->value('ubigeo_id'));
    }

    public function test_moved_agency_is_relinked_by_code_even_with_foreign_user_ids(): void
    {
        $admin = $this->superAdmin();

        $destination = $this->makeAgency(['code' => 'AG-DEST-005', 'name' => 'DESTINO']);
        $this->makeAgency([
            'code' => 'AG-MOVED-006',
            'name' => 'TRASLADADA',
            'has_moved' => true,
            'moved_to_agency_id' => $destination->id,
            'moved_to_address' => 'Nueva dirección 123',
            'move_notice' => 'Nos mudamos.',
            'moved_at' => '2026-03-15',
        ]);

        $backup = app(AgencyBackupService::class)->create();

        // Ids de usuario ajenos + se rompe la correspondencia de ids de agencia
        // reordenando: la relación debe reconstruirse por code, no por id.
        $this->mutateBackupAgencies($backup, function (array $agency): array {
            $agency['created_by'] = 999001;

            return $agency;
        });

        Agency::withoutEvents(fn () => Agency::withTrashed()->forceDelete());

        // Se recrea el destino primero para que reciba un id distinto al original.
        $this->makeAgency(['code' => 'AG-DEST-005', 'name' => 'DESTINO PRE-EXISTENTE']);

        app(AgencyRestoreService::class)->restore($this->restoreFrom($backup, $admin->id));

        $moved = Agency::query()->where('code', 'AG-MOVED-006')->firstOrFail();
        $dest = Agency::query()->where('code', 'AG-DEST-005')->firstOrFail();

        $this->assertTrue((bool) $moved->has_moved);
        $this->assertSame($dest->id, $moved->moved_to_agency_id, 'el traslado se reenlaza por code');
        // Los datos textuales del traslado no se pierden.
        $this->assertSame('Nueva dirección 123', $moved->moved_to_address);
        $this->assertSame('Nos mudamos.', $moved->move_notice);
    }

    public function test_full_restore_from_foreign_installation_loses_no_functional_data(): void
    {
        $admin = $this->superAdmin();
        $this->makeAgency([
            'code' => 'AG-FULL-007',
            'name' => 'AGENCIA COMPLETA',
            'old_name' => 'NOMBRE VIEJO',
            'status' => AgencyStatus::Active,
            'texto_chosen_terrestre' => 'T-COMPLETO',
            'texto_chosen_aereo' => 'A-COMPLETO',
            'category' => 'GRANDE CO',
            'observations' => 'observación completa',
        ]);

        $backup = app(AgencyBackupService::class)->create();
        $this->mutateBackupAgencies($backup, function (array $agency): array {
            $agency['created_by'] = 999001;
            $agency['updated_by'] = 999002;
            $agency['ubigeo_id'] = 424242;

            return $agency;
        });

        Agency::withoutEvents(fn () => Agency::withTrashed()->forceDelete());

        $restore = $this->restoreFrom($backup, $admin->id);
        app(AgencyRestoreService::class)->restore($restore);

        $this->assertSame(AgencyRestoreStatus::Completed, $restore->refresh()->status);

        $agency = Agency::query()->where('code', 'AG-FULL-007')->firstOrFail();
        $this->assertSame('NOMBRE VIEJO', $agency->old_name);
        $this->assertSame('T-COMPLETO', $agency->texto_chosen_terrestre);
        $this->assertSame('A-COMPLETO', $agency->texto_chosen_aereo);
        $this->assertSame('GRANDE CO', $agency->category->value);
        $this->assertSame('observación completa', $agency->observations);
        $this->assertSame('active', $agency->status->value);
        // Las FKs no válidas quedaron saneadas, sin romper la restauración.
        $this->assertSame($admin->id, $agency->created_by);
        $this->assertNull($agency->ubigeo_id);
    }
}
