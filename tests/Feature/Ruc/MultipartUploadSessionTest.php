<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackupUpload;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipartUploadSessionTest extends TestCase
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

    private function postJsonWithCsrf(string $uri, array $data = [])
    {
        $token = 'csrf-token-for-test';

        return $this->withSession(['_token' => $token])
            ->postJson($uri, array_merge($data, ['_token' => $token]));
    }

    private function validManifest(): array
    {
        return [
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
        ];
    }

    public function test_create_session_requires_authentication(): void
    {
        $response = $this->postJsonWithCsrf(route('admin.ruc.backups.multipart.store'), ['manifest' => $this->validManifest()]);

        $response->assertUnauthorized();
    }

    public function test_create_session_requires_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJsonWithCsrf(route('admin.ruc.backups.multipart.store'), ['manifest' => $this->validManifest()]);

        $response->assertForbidden();
    }

    public function test_create_session_returns_upload_uuid_and_total_parts(): void
    {
        $response = $this->actingAs($this->adminUser())->postJsonWithCsrf(route('admin.ruc.backups.multipart.store'), ['manifest' => $this->validManifest()]);

        $response->assertCreated();
        $response->assertJsonStructure(['upload_uuid', 'total_parts', 'already_uploaded_parts']);
        $this->assertSame(2, $response->json('total_parts'));
        $this->assertDatabaseHas('ruc_backup_uploads', ['uuid' => $response->json('upload_uuid'), 'status' => RucBackupUpload::STATUS_PENDING]);
        $this->assertSame(2, RucBackupUpload::first()->parts()->count());
    }

    public function test_invalid_manifest_returns_422_with_clear_message(): void
    {
        $manifest = $this->validManifest();
        unset($manifest['sha256']);

        $response = $this->actingAs($this->adminUser())->postJsonWithCsrf(route('admin.ruc.backups.multipart.store'), ['manifest' => $manifest]);

        $response->assertStatus(422);
        $this->assertStringContainsString('sha256', $response->json('message'));
    }

    public function test_status_endpoint_reports_session_state(): void
    {
        $created = $this->actingAs($user = $this->adminUser())->postJsonWithCsrf(route('admin.ruc.backups.multipart.store'), ['manifest' => $this->validManifest()]);
        $uuid = $created->json('upload_uuid');

        $response = $this->actingAs($user)->getJson(route('admin.ruc.backups.multipart.show', ['upload' => $uuid]));

        $response->assertOk();
        $response->assertJson([
            'uuid' => $uuid,
            'status' => 'pending',
            'uploaded_parts' => [],
            'total_parts' => 2,
        ]);
    }

    public function test_status_endpoint_rejects_other_users_session(): void
    {
        $owner = $this->adminUser();
        $intruder = $this->adminUser();

        $created = $this->actingAs($owner)->postJsonWithCsrf(route('admin.ruc.backups.multipart.store'), ['manifest' => $this->validManifest()]);
        $uuid = $created->json('upload_uuid');

        $response = $this->actingAs($intruder)->getJson(route('admin.ruc.backups.multipart.show', ['upload' => $uuid]));

        $response->assertForbidden();
    }
}
