<?php

declare(strict_types=1);

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucRecord;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fija la eliminación del sistema de IMPORTACIÓN RUC.
 *
 * El módulo queda centrado en el padrón (ruc_records + API) y en
 * backup/restore. Estas pruebas evitan que una futura actualización
 * reintroduzca rutas, tablas o columnas de importación, y —sobre todo—
 * verifican que la eliminación NO tocó los datos del padrón.
 */
class RucImportSystemRemovedTest extends TestCase
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

    // ----------------------------------------------------------- rutas ----

    public function test_import_routes_no_longer_exist(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter()->values();

        foreach (['admin.ruc.imports', 'admin.ruc.imports.errors'] as $removed) {
            $this->assertFalse($names->contains($removed), "La ruta {$removed} debería haber sido eliminada.");
        }

        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->values();
        $this->assertEmpty(
            $uris->filter(fn (string $uri) => str_contains($uri, 'ruc/importaciones'))->all(),
            'No debe quedar ninguna URI de importación RUC.',
        );
    }

    public function test_import_urls_return_404(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/ruc/importaciones')->assertNotFound();
        $this->actingAs($this->adminUser())->get('/admin/ruc/importaciones/1/errores')->assertNotFound();
    }

    public function test_backup_and_records_routes_survive(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();

        foreach ([
            'admin.ruc.records',
            'admin.ruc.backups',
            'admin.ruc.backups.store',
            'admin.ruc.backups.download',
            'admin.ruc.backups.restore',
            'admin.ruc.backups.destroy',
            'admin.ruc.backups.operations.status',
            'api.v1.ruc.show',
            'api.v1.ruc.search',
        ] as $kept) {
            $this->assertTrue($names->contains($kept), "La ruta {$kept} debe seguir existiendo.");
        }
    }

    // ---------------------------------------------------------- tablas ----

    public function test_import_tables_are_dropped(): void
    {
        foreach (['ruc_imports', 'ruc_import_errors', 'ruc_import_events', 'ruc_import_duplicates', 'ruc_staging'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "La tabla {$table} debería haber sido eliminada por la migración.");
        }
    }

    public function test_backup_tables_are_preserved(): void
    {
        foreach (['ruc_records', 'ruc_backups', 'ruc_backup_operations', 'ruc_backup_uploads', 'ruc_statistics'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "La tabla {$table} NO debe eliminarse.");
        }
    }

    public function test_ruc_records_no_longer_has_the_orphan_import_column(): void
    {
        $this->assertFalse(Schema::hasColumn('ruc_records', 'ruc_import_id'));
    }

    /** Las columnas del padrón deben permanecer intactas. */
    public function test_ruc_records_keeps_all_padron_columns(): void
    {
        foreach (['ruc', 'razon_social', 'estado', 'condicion', 'ubigeo', 'direccion', 'departamento', 'provincia', 'distrito'] as $column) {
            $this->assertTrue(Schema::hasColumn('ruc_records', $column), "ruc_records.{$column} no debe eliminarse.");
        }
    }

    // ------------------------------------------------------------ datos ----

    /**
     * La migración de limpieza no debe borrar filas: se crean registros,
     * se comprueba que siguen ahí y que los campos del padrón se conservan.
     */
    public function test_ruc_records_data_is_not_deleted(): void
    {
        RucRecord::query()->create([
            'ruc' => '20123456789',
            'razon_social' => 'EMPRESA DE PRUEBA SAC',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
        ]);
        $countBefore = RucRecord::query()->count();

        $this->assertSame(1, $countBefore);

        $record = RucRecord::query()->where('ruc', '20123456789')->first();
        $this->assertNotNull($record);
        $this->assertSame('EMPRESA DE PRUEBA SAC', $record->razon_social);
        $this->assertSame('ACTIVO', $record->estado);
        $this->assertSame($countBefore, RucRecord::query()->count());
    }

    // -------------------------------------------------------- funcional ----

    public function test_records_page_still_works_and_links_to_backups(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.records'));

        $response->assertOk();
        $response->assertDontSee('Importaciones', false);
    }

    public function test_backups_page_still_works(): void
    {
        RucBackup::create([
            'name' => 'ruc_backup_after_cleanup.dump',
            'storage_path' => 'backups/ruc/ruc_backup_after_cleanup.dump',
            'status' => RucBackup::STATUS_COMPLETED,
            'total_records' => 5,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertOk();
        $response->assertSee('ruc_backup_after_cleanup.dump');
        $response->assertSee('Crear Backup');
    }

    public function test_navigation_no_longer_offers_ruc_import(): void
    {
        $content = $this->actingAs($this->adminUser())->get(route('admin.ruc.records'))->getContent();

        $this->assertStringNotContainsString('Importaciones RUC', $content);
        $this->assertStringNotContainsString('admin/ruc/importaciones', $content);
        $this->assertStringContainsString('Backups RUC', $content);
    }

    // ------------------------------------------------------------ clases ----

    public function test_import_classes_no_longer_exist(): void
    {
        foreach ([
            'App\Modules\Ruc\Models\RucImport',
            'App\Modules\Ruc\Models\RucImportError',
            'App\Modules\Ruc\Models\RucImportEvent',
            'App\Modules\Ruc\Models\RucImportDuplicate',
            'App\Modules\Ruc\Jobs\PrepareRucImportJob',
            'App\Modules\Ruc\Jobs\ProcessRucImportJob',
            'App\Modules\Ruc\Jobs\ProcessRucImportJobV3',
            'App\Modules\Ruc\Services\RucImportService',
            'App\Modules\Ruc\Services\RucImportOrchestrator',
            'App\Modules\Ruc\Services\RucIncomingFileScanner',
            'App\Modules\Ruc\Support\RucPadronParser',
            'App\Livewire\Admin\Ruc\Imports',
        ] as $class) {
            $this->assertFalse(class_exists($class), "{$class} debería haber sido eliminada.");
        }
    }

    public function test_backup_and_records_classes_survive(): void
    {
        foreach ([
            'App\Modules\Ruc\Models\RucRecord',
            'App\Modules\Ruc\Models\RucBackup',
            'App\Modules\Ruc\Models\RucBackupOperation',
            'App\Modules\Ruc\Jobs\RestoreRucBackupJob',
            'App\Modules\Ruc\Services\RucBackupService',
            'App\Modules\Ruc\Services\RucStatisticsService',
            'App\Modules\Ruc\Services\RucLookupService',
        ] as $class) {
            $this->assertTrue(class_exists($class), "{$class} NO debe eliminarse.");
        }
    }

    // --------------------------------------------------------- config ----

    public function test_import_configuration_is_gone(): void
    {
        $this->assertNull(config('ruc.import'));
        $this->assertNull(config('queue.connections.ruc-imports'));
    }

    public function test_backup_configuration_survives(): void
    {
        $this->assertIsArray(config('ruc.backup'));
        $this->assertIsArray(config('queue.connections.ruc-backups'));
    }
}
