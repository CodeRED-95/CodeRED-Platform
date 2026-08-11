<?php

declare(strict_types=1);

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión de UrlGenerationException en /admin/ruc/backups.
 *
 * La vista construía la URL de polling como un "prefijo" llamando a
 * route('admin.ruc.backups.operations.status', ['operation' => '']) y
 * concatenando el UUID en JavaScript. Laravel descarta los parámetros con
 * valor vacío, así que {operation} quedaba sin resolver y la página entera
 * reventaba con HTTP 500 — pero SOLO cuando existía una operación activa,
 * porque el bloque vive dentro de @if($activeRestoreOperation).
 *
 * Estos tests fijan el contrato: si hay operación activa se genera la URL
 * completa con su UUID y arranca el polling; si no la hay, no se genera URL
 * de status ni se arranca polling.
 */
class RucBackupRestoreStatusUiTest extends TestCase
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

    private function completedBackup(string $name = 'ruc_backup_status_test.dump'): RucBackup
    {
        return RucBackup::create([
            'name' => $name,
            'storage_path' => "backups/ruc/{$name}",
            'status' => RucBackup::STATUS_COMPLETED,
            'total_records' => 100,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function restoreOperation(string $status, array $attributes = []): RucBackupOperation
    {
        return RucBackupOperation::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $this->completedBackup()->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => $status,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'progress' => 0,
            'message' => 'En cola',
        ], $attributes));
    }

    /**
     * `@js()` escapa las barras como `\/` dentro del HTML, así que hay que
     * deshacer ese escape antes de comparar contra una URL literal.
     */
    private function unescapedContent(string $content): string
    {
        return str_replace('\\/', '/', $content);
    }

    // ---------------------------------------------------------------- A ----

    public function test_page_loads_without_any_operation(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertOk();
    }

    public function test_page_without_active_operation_does_not_start_polling(): void
    {
        $this->completedBackup();

        $content = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'))->getContent();

        $this->assertStringNotContainsString('rucRestoreProgress(', $content);
        $this->assertStringNotContainsString('setInterval', $content);
        $this->assertStringNotContainsString('backups/operations', $this->unescapedContent($content));
    }

    // ---------------------------------------------------------------- B ----

    public function test_page_with_pending_restore_renders_status_url_with_operation_id(): void
    {
        $operation = $this->restoreOperation(RucBackupOperation::STATUS_PENDING);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertOk();
        $content = $this->unescapedContent($response->getContent());

        $this->assertStringContainsString(
            route('admin.ruc.backups.operations.status', ['operation' => $operation->uuid]),
            $content,
        );
        $this->assertStringContainsString('rucRestoreProgress(', $content);
    }

    // ---------------------------------------------------------------- C ----

    public function test_page_with_running_restore_polls_the_correct_operation(): void
    {
        // Una operación terminada anterior no debe confundir al selector.
        $this->restoreOperation(RucBackupOperation::STATUS_COMPLETED, ['stage' => RucBackupOperation::STAGE_COMPLETED]);
        $running = $this->restoreOperation(RucBackupOperation::STATUS_RUNNING, [
            'stage' => RucBackupOperation::STAGE_RESTORING,
            'progress' => 55,
            'message' => 'Restaurando datos',
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertOk();
        $content = $this->unescapedContent($response->getContent());

        $this->assertStringContainsString(
            route('admin.ruc.backups.operations.status', ['operation' => $running->uuid]),
            $content,
        );
        $this->assertStringNotContainsString($running->uuid.'/status/extra', $content);
        // El progreso persistido llega al render inicial, sin esperar al primer poll.
        $this->assertStringContainsString('55', $content);
    }

    public function test_active_restore_prefers_the_running_operation_over_finished_ones(): void
    {
        $this->restoreOperation(RucBackupOperation::STATUS_FAILED, ['error_message' => 'fallo viejo']);
        $running = $this->restoreOperation(RucBackupOperation::STATUS_RUNNING);

        $active = RucBackupOperation::activeRestore();

        $this->assertNotNull($active);
        $this->assertSame($running->id, $active->id);
    }

    // ---------------------------------------------------------------- D ----

    public function test_completed_operation_leaves_no_active_polling(): void
    {
        $this->restoreOperation(RucBackupOperation::STATUS_COMPLETED, [
            'stage' => RucBackupOperation::STAGE_COMPLETED,
            'progress' => 100,
            'message' => 'Restauración completada',
            'records_after' => 100,
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('rucRestoreProgress(', $content);
        $this->assertStringNotContainsString('setInterval', $content);
        $response->assertSee('Última restauración: completada');
    }

    // ---------------------------------------------------------------- E ----

    public function test_failed_operation_shows_error_and_stops_polling(): void
    {
        $this->restoreOperation(RucBackupOperation::STATUS_FAILED, [
            'stage' => RucBackupOperation::STAGE_FAILED,
            'progress' => 40,
            'message' => 'Restauración fallida',
            'error_message' => 'pg_restore: error: could not open input file',
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertOk();
        $content = $response->getContent();

        $response->assertSee('Última restauración: falló');
        $response->assertSee('pg_restore: error: could not open input file');
        $this->assertStringNotContainsString('rucRestoreProgress(', $content);
        $this->assertStringNotContainsString('setInterval', $content);
    }

    // ------------------------------------------------------------ extras ----

    /**
     * Guard explícito contra la regresión: generar la ruta sin parámetro (o
     * con cadena vacía) debe seguir siendo un error, y la vista jamás debe
     * hacerlo.
     */
    public function test_status_route_requires_the_operation_parameter(): void
    {
        $this->expectException(UrlGenerationException::class);

        route('admin.ruc.backups.operations.status', ['operation' => '']);
    }

    public function test_status_endpoint_and_initial_render_share_the_same_payload_shape(): void
    {
        $operation = $this->restoreOperation(RucBackupOperation::STATUS_RUNNING, [
            'stage' => RucBackupOperation::STAGE_RESTORING,
            'progress' => 70,
            'message' => 'Restaurando datos',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->getJson(route('admin.ruc.backups.operations.status', ['operation' => $operation->uuid]));

        $response->assertOk();
        $this->assertSame(
            array_keys($operation->fresh()->toStatusPayload()),
            array_keys($response->json()),
        );
        // backup_name debe existir desde el primer render, no solo tras el poll.
        $response->assertJsonPath('backup_name', $operation->backup->name);
    }

    /** Recargar la página es un GET puro: no debe crear ni relanzar operaciones. */
    public function test_reloading_the_page_does_not_create_or_restart_operations(): void
    {
        $operation = $this->restoreOperation(RucBackupOperation::STATUS_RUNNING);
        $countBefore = RucBackupOperation::query()->count();

        $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'))->assertOk();
        $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'))->assertOk();

        $this->assertSame($countBefore, RucBackupOperation::query()->count());
        $this->assertSame(RucBackupOperation::STATUS_RUNNING, $operation->fresh()->status);
    }
}
