<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Services\RucBackupMultipartUploadService;
use App\Modules\Ruc\Services\RucBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MultipartResumeTest extends TestCase
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

    private function makeThreePartManifest(): array
    {
        $contents = ['A' => str_repeat('A', 100), 'B' => str_repeat('B', 100), 'C' => str_repeat('C', 40)];

        return [
            [
                'format_version' => 1,
                'tool' => 'ruc-tools',
                'tool_version' => '2.3.0',
                'backup_type' => 'ruc_records',
                'created_at' => now()->toIso8601String(),
                'original_filename' => 'ruc_backup_resume_test.dump',
                'total_records' => 1,
                'total_size_bytes' => 240,
                'part_size_bytes' => 100,
                'total_parts' => 3,
                'sha256' => hash('sha256', $contents['A'].$contents['B'].$contents['C']),
                'parts' => [
                    ['index' => 1, 'filename' => 'ruc_backup_resume_test.dump.part0001', 'size_bytes' => 100, 'sha256' => hash('sha256', $contents['A'])],
                    ['index' => 2, 'filename' => 'ruc_backup_resume_test.dump.part0002', 'size_bytes' => 100, 'sha256' => hash('sha256', $contents['B'])],
                    ['index' => 3, 'filename' => 'ruc_backup_resume_test.dump.part0003', 'size_bytes' => 40, 'sha256' => hash('sha256', $contents['C'])],
                ],
            ],
            $contents,
        ];
    }

    public function test_status_endpoint_reflects_partial_progress_for_resume(): void
    {
        $user = $this->adminUser();
        [$manifest, $contents] = $this->makeThreePartManifest();
        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);

        // Solo se sube la parte 1: la sesión debe "recordar" esto para que
        // el cliente pueda reanudar desde la parte 2 sin volver a subirla.
        $this->actingAs($user)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]),
            ['part' => UploadedFile::fake()->createWithContent('ruc_backup_resume_test.dump.part0001', $contents['A'])]
        );

        $response = $this->actingAs($user)->getJson(route('admin.ruc.backups.multipart.show', ['upload' => $upload->uuid]));

        $response->assertOk();
        $this->assertSame([1], $response->json('uploaded_parts'));
        $this->assertSame(100, $response->json('received_bytes'));
        $this->assertSame(3, $response->json('total_parts'));
        $this->assertSame('uploading', $response->json('status'));
    }

    public function test_previously_verified_parts_are_not_reuploaded_on_resume(): void
    {
        $user = $this->adminUser();
        [$manifest, $contents] = $this->makeThreePartManifest();
        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);

        $route1 = route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]);
        $this->actingAs($user)->postWithCsrf($route1, ['part' => UploadedFile::fake()->createWithContent('ruc_backup_resume_test.dump.part0001', $contents['A'])]);

        $status = $this->actingAs($user)->getJson(route('admin.ruc.backups.multipart.show', ['upload' => $upload->uuid]));
        $uploadedBefore = $status->json('uploaded_parts');

        // Reenviar la MISMA parte 1 (simula que el cliente, al reanudar, no
        // sabe todavía que ya estaba verificada): debe seguir siendo válida
        // y no duplicar ni corromper el estado.
        $again = $this->actingAs($user)->postWithCsrf($route1, ['part' => UploadedFile::fake()->createWithContent('ruc_backup_resume_test.dump.part0001', $contents['A'])]);

        $again->assertOk();
        $this->assertSame($uploadedBefore, [1]);
        $this->assertSame(1, $upload->parts()->where('part_index', 1)->count());
    }

    public function test_completing_remaining_parts_after_partial_upload_finishes_the_backup(): void
    {
        // Contenido real de pg_dump (no texto arbitrario): la última parte
        // dispara el ensamblado, que valida el dump con pg_restore --list
        // — con contenido falso, la aserción de éxito de este test no
        // probaría nada real.
        $user = $this->adminUser();
        $realDump = app(RucBackupService::class)->create($user);
        $contents = file_get_contents($realDump->absolutePath());
        $chunkSize = (int) ceil(strlen($contents) / 3);
        $chunks = str_split($contents, max(1, $chunkSize));

        $parts = [];
        foreach ($chunks as $i => $chunk) {
            $parts[] = [
                'index' => $i + 1,
                'filename' => 'ruc_backup_resume_complete_test.dump.part'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'size_bytes' => strlen($chunk),
                'sha256' => hash('sha256', $chunk),
            ];
        }

        $manifest = [
            'format_version' => 1,
            'tool' => 'ruc-tools',
            'tool_version' => '2.3.0',
            'backup_type' => 'ruc_records',
            'created_at' => now()->toIso8601String(),
            'original_filename' => 'ruc_backup_resume_complete_test.dump',
            'total_records' => 0,
            'total_size_bytes' => strlen($contents),
            'part_size_bytes' => $chunkSize,
            'total_parts' => count($parts),
            'sha256' => hash_file('sha256', $realDump->absolutePath()),
            'parts' => $parts,
        ];

        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);

        $response = null;
        foreach ($parts as $i => $part) {
            $response = $this->actingAs($user)->postWithCsrf(
                route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => $part['index']]),
                ['part' => UploadedFile::fake()->createWithContent($part['filename'], $chunks[$i])]
            );
        }

        $response->assertOk();
        $this->assertNotNull($response->json('ruc_backup_id'));
    }
}
