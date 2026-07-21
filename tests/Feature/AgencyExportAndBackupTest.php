<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Backups;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyBackupStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyBackup;
use App\Modules\Agencies\Services\AgencyBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyExportAndBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    public function test_total_and_filtered_exports_are_valid_streamed_json_not_limited_to_page(): void
    {
        $super = $this->userFor('super-admin');
        Agency::factory()->count(18)->create(['department' => 'Tacna']);
        Agency::factory()->count(3)->create(['department' => 'Lima']);
        Agency::factory()->create(['department' => 'Tacna', 'name' => 'Agencia Ñandú', 'reference' => null, 'is_operations_center' => true]);

        $filteredResponse = $this->actingAs($super)->get(route('admin.agencies.export', ['scope' => 'filtered', 'department' => 'Tacna']));
        $filteredResponse->assertOk()->assertHeader('content-type', 'application/json; charset=UTF-8');
        $filtered = json_decode($filteredResponse->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(19, $filtered['metadata']['record_count']);
        $this->assertTrue($filtered['metadata']['filtered']);
        $this->assertCount(19, $filtered['agencies']);
        $this->assertSame(['Tacna'], array_values(array_unique(array_column($filtered['agencies'], 'departamento'))));
        $this->assertStringContainsString('Ñandú', $filteredResponse->streamedContent());
        $this->assertTrue(collect($filtered['agencies'])->contains(fn (array $agency): bool => $agency['centro_operaciones'] === true));
        $this->assertNull(collect($filtered['agencies'])->firstWhere('agencia', 'Agencia Ñandú')['referencia']);

        $allResponse = $this->get(route('admin.agencies.export', ['scope' => 'all']));
        $all = json_decode($allResponse->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(22, $all['metadata']['record_count']);
        $this->assertMatchesRegularExpression('/attachment; filename=agencias-\d{4}-\d{2}-\d{2}-\d{6}\.json/', (string) $allResponse->headers->get('content-disposition'));
        $this->assertStringNotContainsString('password', $allResponse->streamedContent());
        $this->assertDatabaseHas('activity_logs', ['action' => 'agency_export_created']);
    }

    public function test_unauthorized_roles_cannot_export_or_manage_backups(): void
    {
        foreach (['editor', 'viewer'] as $role) {
            $user = $this->userFor($role);
            $this->actingAs($user)->get(route('admin.agencies.export', ['scope' => 'all']))->assertForbidden();
            $this->get(route('admin.agencies.backups.index'))->assertForbidden();
            Livewire::actingAs($user)->test(Backups::class)->assertForbidden();
        }
    }

    public function test_backup_is_private_complete_atomic_and_checksum_is_verifiable(): void
    {
        $super = $this->userFor('super-admin');
        Agency::factory()->count(3)->create();
        $this->actingAs($super);
        $backup = app(AgencyBackupService::class)->create($super->id);

        $this->assertSame(AgencyBackupStatus::Completed, $backup->status);
        $this->assertSame(3, $backup->record_count);
        Storage::disk('local')->assertExists($backup->path);
        $contents = Storage::disk('local')->get($backup->path);
        $json = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(3, $json['data']['agencies']);
        $this->assertSame(3, $json['metadata']['record_count']);
        $this->assertSame(strlen($contents), $backup->size_bytes);
        $this->assertSame(hash('sha256', $contents), $backup->checksum_sha256);
        $this->assertSame('integrity_ok', app(AgencyBackupService::class)->verify($backup));
        Storage::disk('local')->put($backup->path, $contents.'alterado');
        $this->assertSame('altered', app(AgencyBackupService::class)->verify($backup));
        $this->assertStringNotContainsString('password', $contents);
        $this->assertStringNotContainsString('api_key', $contents);
        $this->assertFalse(Storage::disk('local')->exists($backup->path.'.part'));
    }

    public function test_authorized_download_delete_command_and_path_traversal_protection(): void
    {
        $super = $this->userFor('super-admin');
        Agency::factory()->create();
        $this->actingAs($super);
        $backup = app(AgencyBackupService::class)->create($super->id);
        $this->get(route('admin.agencies.backups.download', $backup))->assertOk()->assertDownload($backup->filename);

        $unsafe = AgencyBackup::query()->create([
            'filename' => 'passwd.json', 'disk' => 'local', 'path' => '../passwd.json',
            'status' => AgencyBackupStatus::Completed, 'created_by' => $super->id,
        ]);
        $this->get(route('admin.agencies.backups.download', $unsafe))->assertNotFound();

        $this->artisan('agencies:backup', ['--no-cleanup' => true])->assertSuccessful()->expectsOutputToContain('SHA-256:');
        app(AgencyBackupService::class)->delete($backup);
        Storage::disk('local')->assertMissing($backup->path);
        $this->assertDatabaseMissing('agency_backups', ['id' => $backup->id]);
    }

    private function userFor(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));

        return $user;
    }
}
