<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucRecord;
use App\Modules\Ruc\Services\RucBackupService;
use App\Modules\Ruc\Services\RucChunkedBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * DatabaseTruncation (no RefreshDatabase): el restore real hace TRUNCATE vía
 * un proceso `psql` externo. Si Laravel envolviera el test en una
 * transacción sin confirmar (RefreshDatabase), ese TRUNCATE externo nunca
 * podría tomar el lock exclusivo mientras la conexión de PHP -bloqueada
 * esperando a psql- sigue reteniendo la transacción abierta: deadlock
 * garantizado. DatabaseTruncation confirma (commit) los datos entre tests,
 * visibles para el proceso externo.
 */
class RucBackupRestoreTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    /** Ver RucBackupCreateTest::postWithCsrf() / PublicTokenRequestWebTest. */
    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = 'csrf-token-for-test';

        return $this->withSession(['_token' => $token])->post($uri, array_merge($data, ['_token' => $token]));
    }

    private function service(): RucBackupService
    {
        return app(RucBackupService::class);
    }

    private function chunkedService(): RucChunkedBackupService
    {
        return app(RucChunkedBackupService::class);
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

        $response = $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.restore', $backup));

        $response->assertForbidden();
    }

    /**
     * Dos restores a la vez harían TRUNCATE sobre ruc_records desde procesos
     * psql distintos. Cache::lock('ruc-restore-process') debe bloquear el
     * segundo intento sin tocar los datos.
     */
    public function test_concurrent_restore_is_blocked(): void
    {
        $this->seedRecords(2, '27');
        $backup = $this->service()->create();

        $lock = Cache::lock('ruc-restore-process', 3600);
        $this->assertTrue($lock->get(), 'No se pudo simular un restore ya en curso.');

        try {
            $countBefore = DB::table('ruc_records')->count();

            $response = $this->actingAs($this->adminUser())->postWithCsrf(route('admin.ruc.backups.restore', $backup));

            $response->assertSessionHas('error');
            $this->assertStringContainsString('restauración', mb_strtolower(session('error')));
            $this->assertSame($countBefore, DB::table('ruc_records')->count());
        } finally {
            $lock->release();
        }
    }

    public function test_checksum_is_validated_before_restoring(): void
    {
        $this->seedRecords(3, '20');
        $backup = $this->service()->create();
        $originalChecksum = hash_file('sha256', $backup->absolutePath());
        self::assertNotFalse(file_put_contents($backup->absolutePath(), file_get_contents($backup->absolutePath())."\ncorrupted"));
        self::assertNotSame($originalChecksum, hash_file('sha256', $backup->absolutePath()));

        $countBefore = DB::table('ruc_records')->count();

        $response = $this->actingAs($this->adminUser())->postWithCsrf(route('admin.ruc.backups.restore', $backup));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('checksum', mb_strtolower(session('error')));
        $this->assertSame($countBefore, DB::table('ruc_records')->count());
    }

    public function test_dump_of_another_table_is_rejected_on_restore(): void
    {
        $this->seedRecords(2, '21');
        $backup = $this->chunkedService()->create();
        $tmpPath = tempnam(sys_get_temp_dir(), 'other').'.rucbackup';
        copy($backup->absolutePath(), $tmpPath);

        $zip = new ZipArchive;
        $zip->open($tmpPath);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['source_table'] = 'users';
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $backup = RucBackup::create([
            'name' => 'other.rucbackup',
            'storage_path' => 'backups/ruc/'.basename($tmpPath),
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => hash_file('sha256', $tmpPath),
            'file_size_bytes' => filesize($tmpPath),
        ]);
        copy($tmpPath, $backup->absolutePath());

        $countBefore = DB::table('ruc_records')->count();

        $response = $this->actingAs($this->adminUser())->postWithCsrf(route('admin.ruc.backups.restore', $backup));

        $response->assertSessionHas('error');
        $this->assertSame($countBefore, DB::table('ruc_records')->count());

        @unlink($tmpPath);
    }

    public function test_safety_backup_is_created_before_restoring(): void
    {
        $this->seedRecords(3, '22');
        $backup = $this->service()->create();

        $safetyCountBefore = RucBackup::where('backup_type', RucBackup::TYPE_SAFETY)->count();

        $this->actingAs($this->adminUser())->postWithCsrf(route('admin.ruc.backups.restore', $backup));

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

        $response = $this->actingAs($this->adminUser())->postWithCsrf(route('admin.ruc.backups.restore', $backup));

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

        $corruptedRelative = 'backups/ruc/corrupted_test.rucbackup';
        $corruptedAbsolute = Storage::disk('local')->path($corruptedRelative);
        copy($goodBackup->absolutePath(), $corruptedAbsolute);

        $zip = new ZipArchive;
        $zip->open($corruptedAbsolute);
        $firstChunk = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName !== 'manifest.json') {
                $firstChunk = $entryName;
                break;
            }
        }

        self::assertNotNull($firstChunk);
        $zip->addFromString($firstChunk, $zip->getFromName($firstChunk)."\ncorrupted");
        $zip->close();

        $truncatedBackup = RucBackup::create([
            'name' => 'corrupted.rucbackup',
            'storage_path' => $corruptedRelative,
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => hash_file('sha256', $corruptedAbsolute),
            'file_size_bytes' => filesize($corruptedAbsolute),
            'total_records' => 5,
        ]);

        $countBefore = DB::table('ruc_records')->count();
        $this->assertGreaterThan(0, $countBefore);

        $response = $this->actingAs($this->adminUser())->postWithCsrf(route('admin.ruc.backups.restore', $truncatedBackup));

        $response->assertSessionHas('error');
        $this->assertSame($countBefore, DB::table('ruc_records')->count(), 'ruc_records debe quedar EXACTAMENTE como estaba tras un restore fallido');

        @unlink($corruptedAbsolute);
    }

    public function test_original_backup_is_not_deleted_after_a_failed_restore(): void
    {
        $this->seedRecords(2, '26');
        $backup = $this->service()->create();

        $garbageRelative = 'backups/ruc/garbage_test.dump';
        $garbageAbsolute = Storage::disk('local')->path($garbageRelative);
        file_put_contents($garbageAbsolute, 'no es un dump');
        $garbageBackup = RucBackup::create([
            'name' => 'garbage.dump',
            'storage_path' => $garbageRelative,
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => hash_file('sha256', $garbageAbsolute),
            'file_size_bytes' => filesize($garbageAbsolute),
        ]);

        $this->actingAs($this->adminUser())->postWithCsrf(route('admin.ruc.backups.restore', $garbageBackup));

        $this->assertFileExists($backup->absolutePath());
        $this->assertDatabaseHas('ruc_backups', ['id' => $backup->id]);

        @unlink($garbageAbsolute);
    }
}
