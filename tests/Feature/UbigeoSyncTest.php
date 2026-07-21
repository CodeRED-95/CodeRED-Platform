<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\Ubigeo;
use App\Modules\Ruc\Services\UbigeoSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UbigeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UbigeoSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ubigeos.snapshot', storage_path('framework/testing/ubigeos-alanube.json'));
    }

    public function test_download_is_validated_upserted_and_idempotent(): void
    {
        config()->set('ubigeos.minimum_rows', 3);
        Http::fake(['*' => Http::response($this->validHtml())]);

        $first = app(UbigeoSyncService::class)->sync();
        $second = app(UbigeoSyncService::class)->sync(dryRun: true);

        $this->assertSame(3, $first['inserted']);
        $this->assertSame(3, $second['ignored']);
        $this->assertDatabaseHas('ubigeos', ['codigo' => '150137', 'distrito' => 'SANTA ANITA', 'source' => 'alanube']);
        $this->assertDatabaseHas('ubigeos', ['codigo' => '150140', 'distrito' => 'SANTIAGO DE SURCO']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('User-Agent', 'CodeRED-Platform/1.0'));
    }

    public function test_dry_run_does_not_write_and_incomplete_or_duplicate_catalog_is_rejected(): void
    {
        config()->set('ubigeos.minimum_rows', 3);
        Http::fake(['*' => Http::response($this->validHtml())]);
        app(UbigeoSyncService::class)->sync(dryRun: true);
        $this->assertDatabaseCount('ubigeos', 0);

        config()->set('ubigeos.minimum_rows', 4);
        $this->expectExceptionMessage('parece incompleto');
        app(UbigeoSyncService::class)->sync();
    }

    public function test_http_errors_and_timeouts_are_controlled(): void
    {
        config()->set('ubigeos.sources.alanube.retries', 0);
        Http::fake(['*' => Http::response('', 500)]);
        try {
            app(UbigeoSyncService::class)->sync();
            $this->fail('La respuesta 500 debía fallar.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Alanube', $exception->getMessage());
        }

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->expectException(ConnectionException::class);
        app(UbigeoSyncService::class)->sync();
    }

    public function test_snapshot_seeder_and_no_download_command_work_offline(): void
    {
        Http::preventStrayRequests();
        $this->seed(UbigeoSeeder::class);
        $this->assertSame(1874, Ubigeo::query()->count());
        config()->set('ubigeos.snapshot', database_path('data/ubigeos_alanube.json'));
        $this->assertSame(0, Artisan::call('ubigeos:sync', ['--dry-run' => true, '--no-download' => true]));
        $this->assertStringContainsString('1874', Artisan::output());
    }

    public function test_only_super_administrator_can_open_ubigeo_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $super = User::factory()->create();
        $super->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        $this->actingAs($super)->get('/admin/settings/ubigeos')->assertOk()->assertSee('Catálogo de UBIGEO');
        $this->actingAs(User::factory()->create())->get('/admin/settings/ubigeos')->assertForbidden();
    }

    private function validHtml(): string
    {
        return '<table><tr><th>Código</th></tr>'
            .'<tr><td>010101</td><td>-</td><td>AMAZONAS</td><td>CHACHAPOYAS</td><td>CHACHAPOYAS</td><td>CHACHAPOYAS</td></tr>'
            .'<tr><td>150137</td><td>-</td><td>LIMA</td><td>LIMA</td><td>SANTA ANITA</td><td>SANTA ANITA</td></tr>'
            .'<tr><td>150140</td><td>-</td><td>LIMA</td><td>LIMA</td><td>SANTIAGO DE SURCO</td><td>SANTIAGO DE SURCO</td></tr></table>';
    }
}
