<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupUpload;
use App\Modules\Ruc\Services\RucBackupMultipartUploadService;
use App\Modules\Ruc\Services\RucBackupService;
use App\Modules\Ruc\Services\RucChunkedBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class MultipartCompleteTest extends TestCase
{
    use RefreshDatabase;

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

    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = 'csrf-token-for-test';

        return $this->withSession(['_token' => $token])->post($uri, array_merge($data, ['_token' => $token]));
    }

    /**
     * Divide un dump REAL (generado por pg_dump vía RucBackupService::create)
     * en partes pequeñas y arma el manifest correspondiente — mismo formato
     * exacto que packages/ruc-tools, sin usar los 443MB reales en tests.
     *
     * @return array{0: array, 1: array<int, string>} [manifest, [index => contenido de la parte]]
     */
    private function splitRealDumpIntoManifest(string $dumpPath, int $partSize, string $originalFilename): array
    {
        $contents = file_get_contents($dumpPath);
        $totalSize = strlen($contents);
        $chunks = str_split($contents, $partSize);

        $parts = [];
        foreach ($chunks as $i => $chunk) {
            $parts[] = [
                'index' => $i + 1,
                'filename' => "{$originalFilename}.part".str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'size_bytes' => strlen($chunk),
                'sha256' => hash('sha256', $chunk),
            ];
        }

        $manifest = [
            'format_version' => 1,
            'tool' => 'ruc-tools',
            'tool_version' => '2.3.0-test',
            'backup_type' => 'ruc_records',
            'created_at' => now()->toIso8601String(),
            'original_filename' => $originalFilename,
            'total_records' => 0,
            'total_size_bytes' => $totalSize,
            'part_size_bytes' => $partSize,
            'total_parts' => count($parts),
            'sha256' => hash_file('sha256', $dumpPath),
            'parts' => $parts,
        ];

        return [$manifest, $chunks];
    }

    private function uploadAllParts(User $user, RucBackupUpload $upload, array $manifest, array $chunks): TestResponse
    {
        $response = null;
        foreach ($manifest['parts'] as $i => $part) {
            $file = UploadedFile::fake()->createWithContent($part['filename'], $chunks[$i]);
            $response = $this->actingAs($user)->postWithCsrf(
                route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => $part['index']]),
                ['part' => $file]
            );
        }

        return $response;
    }

    public function test_full_multipart_flow_assembles_a_valid_backup(): void
    {
        $user = $this->adminUser();
        $realDump = app(RucBackupService::class)->create($user);
        [$manifest, $chunks] = $this->splitRealDumpIntoManifest($realDump->absolutePath(), 500, 'ruc_backup_multipart_test.dump');

        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);
        $this->assertGreaterThan(1, $upload->total_parts, 'El fixture debe producir más de una parte para ser una prueba real de multipart.');

        $lastResponse = $this->uploadAllParts($user, $upload, $manifest, $chunks);

        $lastResponse->assertOk();
        $upload->refresh();
        $this->assertSame(RucBackupUpload::STATUS_COMPLETED, $upload->status);
        $this->assertNotNull($upload->ruc_backup_id);

        $backup = RucBackup::find($upload->ruc_backup_id);
        $this->assertNotNull($backup);
        $this->assertSame(RucBackup::STATUS_COMPLETED, $backup->status);
        $this->assertSame(RucBackup::TYPE_UPLOADED, $backup->backup_type);
        $this->assertSame($manifest['sha256'], $backup->checksum_sha256);
        $this->assertTrue($backup->fileExists());
        $this->assertSame(hash_file('sha256', $backup->absolutePath()), $manifest['sha256']);
    }

    public function test_temporary_parts_are_deleted_after_successful_assembly(): void
    {
        $user = $this->adminUser();
        $realDump = app(RucBackupService::class)->create($user);
        [$manifest, $chunks] = $this->splitRealDumpIntoManifest($realDump->absolutePath(), 500, 'ruc_backup_multipart_cleanup_test.dump');

        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);
        $tempDir = $upload->temporary_directory;
        $this->uploadAllParts($user, $upload, $manifest, $chunks);

        $this->assertFalse(Storage::disk('local')->exists($tempDir));
    }

    public function test_manifest_copy_is_saved_alongside_the_final_backup(): void
    {
        $user = $this->adminUser();
        $realDump = app(RucBackupService::class)->create($user);
        [$manifest, $chunks] = $this->splitRealDumpIntoManifest($realDump->absolutePath(), 500, 'ruc_backup_multipart_manifest_test.dump');

        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);
        $this->uploadAllParts($user, $upload, $manifest, $chunks);

        $backup = RucBackup::find($upload->fresh()->ruc_backup_id);
        $manifestPath = 'backups/ruc/'.pathinfo($backup->name, PATHINFO_FILENAME).'.manifest.json';
        $this->assertTrue(Storage::disk('local')->exists($manifestPath));
    }

    public function test_dump_of_another_table_is_rejected_at_assembly(): void
    {
        $user = $this->adminUser();
        $backup = app(RucChunkedBackupService::class)->create($user);
        $tmpPath = tempnam(sys_get_temp_dir(), 'other').'.rucbackup';
        copy($backup->absolutePath(), $tmpPath);

        $zip = new ZipArchive;
        $zip->open($tmpPath);
        $manifestZip = json_decode($zip->getFromName('manifest.json'), true);
        $manifestZip['source_table'] = 'users';
        $zip->addFromString('manifest.json', json_encode($manifestZip, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->close();

        [$manifest, $chunks] = $this->splitRealDumpIntoManifest($tmpPath, 500, 'ruc_backup_other_table_test.dump');
        $countBefore = RucBackup::count();

        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);
        $lastResponse = $this->uploadAllParts($user, $upload, $manifest, $chunks);

        $lastResponse->assertStatus(422);
        $upload->refresh();
        $this->assertSame(RucBackupUpload::STATUS_FAILED, $upload->status);
        $this->assertSame($countBefore, RucBackup::count());

        @unlink($tmpPath);
    }
}
