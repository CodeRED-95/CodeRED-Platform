<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Services\RucBackupMultipartUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipartManifestTest extends TestCase
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

    private function service(): RucBackupMultipartUploadService
    {
        return app(RucBackupMultipartUploadService::class);
    }

    private function validManifest(array $overrides = []): array
    {
        return array_replace([
            'format_version' => 1,
            'tool' => 'ruc-tools',
            'tool_version' => '2.3.0',
            'backup_type' => 'ruc_records',
            'created_at' => now()->toIso8601String(),
            'original_filename' => 'ruc_backup_2026-08-08-131307.dump',
            'total_records' => 100,
            'total_size_bytes' => 200,
            'part_size_bytes' => 100,
            'total_parts' => 2,
            'sha256' => str_repeat('a', 64),
            'parts' => [
                ['index' => 1, 'filename' => 'ruc_backup_2026-08-08-131307.dump.part0001', 'size_bytes' => 100, 'sha256' => str_repeat('b', 64)],
                ['index' => 2, 'filename' => 'ruc_backup_2026-08-08-131307.dump.part0002', 'size_bytes' => 100, 'sha256' => str_repeat('c', 64)],
            ],
        ], $overrides);
    }

    public function test_valid_manifest_creates_a_session(): void
    {
        $upload = $this->service()->createSession($this->validManifest(), $this->adminUser());

        $this->assertNotNull($upload->uuid);
        $this->assertSame(2, $upload->total_parts);
        $this->assertSame(2, $upload->parts()->count());
    }

    public function test_unsupported_format_version_is_rejected(): void
    {
        $this->expectExceptionMessage('format_version');
        $this->service()->createSession($this->validManifest(['format_version' => 999]), $this->adminUser());
    }

    public function test_wrong_backup_type_is_rejected(): void
    {
        $this->expectExceptionMessage('backup_type');
        $this->service()->createSession($this->validManifest(['backup_type' => 'agencies']), $this->adminUser());
    }

    public function test_missing_key_is_rejected(): void
    {
        $manifest = $this->validManifest();
        unset($manifest['sha256']);

        $this->expectExceptionMessage('sha256');
        $this->service()->createSession($manifest, $this->adminUser());
    }

    public function test_part_count_mismatch_is_rejected(): void
    {
        $manifest = $this->validManifest(['total_parts' => 5]);

        $this->expectExceptionMessageMatches('/no coincide/');
        $this->service()->createSession($manifest, $this->adminUser());
    }

    public function test_oversized_part_size_is_rejected(): void
    {
        $manifest = $this->validManifest(['part_size_bytes' => 999999999999]);

        $this->expectExceptionMessageMatches('/excede el máximo/');
        $this->service()->createSession($manifest, $this->adminUser());
    }

    public function test_excessive_total_parts_is_rejected(): void
    {
        $manifest = $this->validManifest(['total_parts' => 100000]);
        // Ajustar parts[] a un array del mismo tamaño sería carísimo de
        // construir; el límite de total_parts se evalúa antes de validar
        // el array completo.
        $this->expectExceptionMessageMatches('/total_parts/');
        $this->service()->createSession($manifest, $this->adminUser());
    }

    public function test_path_traversal_in_original_filename_is_rejected(): void
    {
        $manifest = $this->validManifest(['original_filename' => '../../etc/passwd']);

        $this->expectExceptionMessageMatches('/caracteres no permitidos|ruta/');
        $this->service()->createSession($manifest, $this->adminUser());
    }

    public function test_path_traversal_in_part_filename_is_rejected(): void
    {
        $manifest = $this->validManifest();
        $manifest['parts'][0]['filename'] = '../../../tmp/evil.dump.part0001';

        $this->expectExceptionMessageMatches('/caracteres no permitidos|ruta/');
        $this->service()->createSession($manifest, $this->adminUser());
    }

    public function test_non_sequential_part_indexes_are_rejected(): void
    {
        $manifest = $this->validManifest();
        $manifest['parts'][1]['index'] = 3; // hueco: 1, 3 en vez de 1, 2

        $this->expectExceptionMessageMatches('/consecutivos/');
        $this->service()->createSession($manifest, $this->adminUser());
    }

    public function test_invalid_sha256_format_is_rejected(): void
    {
        $manifest = $this->validManifest(['sha256' => 'not-a-hash']);

        $this->expectExceptionMessageMatches('/sha256/');
        $this->service()->createSession($manifest, $this->adminUser());
    }
}
