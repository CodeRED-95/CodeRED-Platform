<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackupUploadPart;
use App\Modules\Ruc\Services\RucBackupMultipartUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MultipartPartUploadTest extends TestCase
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

    /** Crea una sesión real con dos partes de contenido conocido. */
    private function makeSession(User $user): array
    {
        $partAContent = str_repeat('A', 100);
        $partBContent = str_repeat('B', 50);

        $manifest = [
            'format_version' => 1,
            'tool' => 'ruc-tools',
            'tool_version' => '2.3.0',
            'backup_type' => 'ruc_records',
            'created_at' => now()->toIso8601String(),
            'original_filename' => 'ruc_backup_test.dump',
            'total_records' => 1,
            'total_size_bytes' => 150,
            'part_size_bytes' => 100,
            'total_parts' => 2,
            'sha256' => hash('sha256', $partAContent.$partBContent),
            'parts' => [
                ['index' => 1, 'filename' => 'ruc_backup_test.dump.part0001', 'size_bytes' => 100, 'sha256' => hash('sha256', $partAContent)],
                ['index' => 2, 'filename' => 'ruc_backup_test.dump.part0002', 'size_bytes' => 50, 'sha256' => hash('sha256', $partBContent)],
            ],
        ];

        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);

        return [$upload, $partAContent, $partBContent];
    }

    private function fakePart(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_valid_part_is_accepted_and_verified(): void
    {
        $user = $this->adminUser();
        [$upload, $partAContent] = $this->makeSession($user);

        $response = $this->actingAs($user)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]),
            ['part' => $this->fakePart('ruc_backup_test.dump.part0001', $partAContent)]
        );

        $response->assertOk();
        $this->assertDatabaseHas('ruc_backup_upload_parts', [
            'upload_id' => $upload->id,
            'part_index' => 1,
            'status' => RucBackupUploadPart::STATUS_VERIFIED,
        ]);
    }

    public function test_wrong_checksum_is_rejected_with_422(): void
    {
        $user = $this->adminUser();
        [$upload] = $this->makeSession($user);

        $response = $this->actingAs($user)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]),
            ['part' => $this->fakePart('ruc_backup_test.dump.part0001', str_repeat('X', 100))]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Checksum incorrecto', $response->json('message'));
        $this->assertDatabaseHas('ruc_backup_upload_parts', ['upload_id' => $upload->id, 'part_index' => 1, 'status' => RucBackupUploadPart::STATUS_FAILED]);
    }

    public function test_wrong_size_is_rejected(): void
    {
        $user = $this->adminUser();
        [$upload] = $this->makeSession($user);

        $response = $this->actingAs($user)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]),
            ['part' => $this->fakePart('ruc_backup_test.dump.part0001', str_repeat('A', 30))]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Tamaño incorrecto', $response->json('message'));
    }

    public function test_unexpected_filename_is_rejected(): void
    {
        $user = $this->adminUser();
        [$upload, $partAContent] = $this->makeSession($user);

        $response = $this->actingAs($user)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]),
            ['part' => $this->fakePart('otro_archivo.dump.part0001', $partAContent)]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Nombre de archivo inesperado', $response->json('message'));
    }

    public function test_invalid_part_index_is_rejected(): void
    {
        $user = $this->adminUser();
        [$upload, $partAContent] = $this->makeSession($user);

        $response = $this->actingAs($user)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 99]),
            ['part' => $this->fakePart('ruc_backup_test.dump.part0099', $partAContent)]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Índice de parte inválido', $response->json('message'));
    }

    public function test_uploading_an_already_verified_part_again_is_idempotent(): void
    {
        $user = $this->adminUser();
        [$upload, $partAContent] = $this->makeSession($user);

        $route = route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]);
        $first = $this->actingAs($user)->postWithCsrf($route, ['part' => $this->fakePart('ruc_backup_test.dump.part0001', $partAContent)]);
        $second = $this->actingAs($user)->postWithCsrf($route, ['part' => $this->fakePart('ruc_backup_test.dump.part0001', $partAContent)]);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(1, RucBackupUploadPart::where('upload_id', $upload->id)->where('part_index', 1)->where('status', RucBackupUploadPart::STATUS_VERIFIED)->count());
    }

    public function test_part_upload_rejects_another_users_session(): void
    {
        $owner = $this->adminUser();
        $intruder = $this->adminUser();
        [$upload, $partAContent] = $this->makeSession($owner);

        $response = $this->actingAs($intruder)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]),
            ['part' => $this->fakePart('ruc_backup_test.dump.part0001', $partAContent)]
        );

        $response->assertStatus(422); // el servicio lanza excepción de negocio, capturada como 422 por el controlador
        $this->assertStringContainsString('no te pertenece', $response->json('message'));
    }

    public function test_stored_part_filename_never_uses_client_supplied_name(): void
    {
        $user = $this->adminUser();
        [$upload, $partAContent] = $this->makeSession($user);

        $this->actingAs($user)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]),
            ['part' => $this->fakePart('ruc_backup_test.dump.part0001', $partAContent)]
        );

        $part = RucBackupUploadPart::where('upload_id', $upload->id)->where('part_index', 1)->first();
        $this->assertStringNotContainsString('ruc_backup_test', $part->storage_path);
        $this->assertStringContainsString('part0001', $part->storage_path);
    }
}
