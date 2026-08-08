<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucRecord;
use App\Modules\Ruc\Services\RucBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RucBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    private function service(): RucBackupService
    {
        return app(RucBackupService::class);
    }

    private function seedRecords(int $count, string $prefix): void
    {
        for ($i = 0; $i < $count; $i++) {
            RucRecord::create([
                'ruc' => $prefix.str_pad((string) $i, 9, '0', STR_PAD_LEFT),
                'razon_social' => "EMPRESA {$i}",
            ]);
        }
    }

    public function test_restore_requires_permission(): void
    {
        $backup = $this->service()->create();
        $user = User::factory()->create(); // sin permiso

        $response = $this->actingAs($user)->post(route('admin.ruc.backups.restore', $backup));

        $response->assertForbidden();
    }

    public function test_checksum_is_validated_before_restoring(): void
    {
        $this->seedRecords(3, '20');
        $backup = $this->service()->create();
        $backup->update(['checksum_sha256' => str_repeat('0', 64)]); // corrompido

        $countBefore = DB::table('ruc_records')->count();

        $response = $this->actingAs($this->adminUser())->post(route('admin.ruc.backups.restore', $backup));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('checksum', mb_strtolower(session('error')));
        $this->assertSame($countBefore, DB::table('ruc_records')->count());
    }

    public function test_dump_of_another_table_is_rejected_on_restore(): void
    {
        $this->seedRecords(2, '21');

        $tmpPath = tempnam(sys_get_temp_dir(), 'other').'.dump';
        $process = new \Symfony\Component\Process\Process([
            'pg_dump',
            '--host='.config('database.connections.pgsql.host'),
            '--port='.config('database.connections.pgsql.port', 5432),
            '--username='.config('database.connections.pgsql.username'),
            '--table=users',
            '--format=custom',
            '--data-only',
            '--file='.$tmpPath,
            config('database.connections.pgsql.database'),
        ]);
        $process->setEnv(['PGPASSWORD' => config('database.connections.pgsql.password')]);
        $process->mustRun();

        $backup = RucBackup::create([
            'name' => 'other.dump',
            'storage_path' => 'backups/ruc/'.basename($tmpPath),
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => hash_file('sha256', $tmpPath),
            'file_size_bytes' => filesize($tmpPath),
        ]);
        copy($tmpPath, $backup->absolutePath());

        $countBefore = DB::table('ruc_records')->count();

        $response = $this->actingAs($this->adminUser())->post(route('admin.ruc.backups.restore', $backup));

        $response->assertSessionHas('error');
        $this->assertSame($countBefore, DB::table('ruc_records')->count());

        @unlink($tmpPath);
    }

    public function test_safety_backup_is_created_before_restoring(): void
    {
        $this->seedRecords(3, '22');
        $backup = $this->service()->create();

        $safetyCountBefore = RucBackup::where('backup_type', RucBackup::TYPE_SAFETY)->count();

        $this->actingAs($this->adminUser())->post(route('admin.ruc.backups.restore', $backup));

        $this->assertSame($safetyCountBefore + 1, RucBackup::where('backup_type', RucBackup::TYPE_SAFETY)->count());

        $safety = RucBackup::where('backup_type', RucBackup::TYPE_SAFETY)->latest('id')->first();
        $this->assertTrue($safety->fileExists());
        $this->assertSame(RucBackup::STATUS_COMPLETED, $safety->status);
    }

    public function test_restore_replaces_data_correctly(): void
    {
        $this->seedRecords(3, '23');
        $backup = $this->service()->create();

        RucRecord::query()->delete();
        $this->seedRecords(1, '24'); // estado "actual" distinto al del backup

        $response = $this->actingAs($this->adminUser())->post(route('admin.ruc.backups.restore', $backup));

        $response->assertSessionHas('success');
        $this->assertSame(3, DB::table('ruc_records')->count());
        $this->assertDatabaseHas('ruc_records', ['ruc' => '23000000000']);
        $this->assertDatabaseMissing('ruc_records', ['ruc' => '24000000000']);
    }

    /**
     * El caso más importante: si la restauración falla, ruc_records debe
     * quedar EXACTAMENTE como estaba (atomicidad vía TRUNCATE + COPY dentro
     * de una sola transacción de psql).
     */
    public function test_failed_restore_preserves_existing_data(): void
    {
        $this->seedRecords(5, '25');
        $goodBackup = $this->service()->create();

        // Truncar el archivo a la mitad: pasa la validación de formato
        // (pg_restore --list puede leer el TOC) pero el restore real falla
        // al copiar datos incompletos.
        $goodSize = filesize($goodBackup->absolutePath());
        $truncatedRelative = 'backups/ruc/truncated_test.dump';
        $truncatedAbsolute = \Illuminate\Support\Facades\Storage::disk('local')->path($truncatedRelative);
        file_put_contents($truncatedAbsolute, substr(file_get_contents($goodBackup->absolutePath()), 0, (int) ($goodSize * 0.6)));

        $truncatedBackup = RucBackup::create([
            'name' => 'truncated.dump',
            'storage_path' => $truncatedRelative,
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => hash_file('sha256', $truncatedAbsolute),
            'file_size_bytes' => filesize($truncatedAbsolute),
            'total_records' => 5,
        ]);

        $countBefore = DB::table('ruc_records')->count();
        $this->assertGreaterThan(0, $countBefore);

        $response = $this->actingAs($this->adminUser())->post(route('admin.ruc.backups.restore', $truncatedBackup));

        $response->assertSessionHas('error');
        $this->assertSame($countBefore, DB::table('ruc_records')->count(), 'ruc_records debe quedar EXACTAMENTE como estaba tras un restore fallido');

        @unlink($truncatedAbsolute);
    }

    public function test_original_backup_is_not_deleted_after_a_failed_restore(): void
    {
        $this->seedRecords(2, '26');
        $backup = $this->service()->create();

        $garbageRelative = 'backups/ruc/garbage_test.dump';
        $garbageAbsolute = \Illuminate\Support\Facades\Storage::disk('local')->path($garbageRelative);
        file_put_contents($garbageAbsolute, 'no es un dump');
        $garbageBackup = RucBackup::create([
            'name' => 'garbage.dump',
            'storage_path' => $garbageRelative,
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => hash_file('sha256', $garbageAbsolute),
            'file_size_bytes' => filesize($garbageAbsolute),
        ]);

        $this->actingAs($this->adminUser())->post(route('admin.ruc.backups.restore', $garbageBackup));

        $this->assertFileExists($backup->absolutePath());
        $this->assertDatabaseHas('ruc_backups', ['id' => $backup->id]);

        @unlink($garbageAbsolute);
    }
}
