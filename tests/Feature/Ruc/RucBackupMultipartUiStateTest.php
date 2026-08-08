<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión estructural del bug reportado: en "Backup dividido" (import
 * multipart), los tres estados terminales (completed/failed/cancelled)
 * aparecían simultáneamente porque cada uno usaba x-show="stage === '...'"
 * directamente sobre <x-ui.alert>, cuyo x-show interno ("visible") colapsaba
 * el externo por duplicado de atributo (ver DesignSystemComponentsTest).
 *
 * PHPUnit no ejecuta Alpine/JS, así que no puede verificar reactividad en
 * vivo (para eso hace falta un navegador real — ver reporte final). Lo que
 * SÍ puede y debe verificar aquí es la estructura: cada estado vive dentro
 * de su propio <template x-if="stage === '...'">, que Alpine NUNCA inserta
 * en el DOM real hasta que la condición es verdadera (a diferencia de
 * x-show, que sí inserta el elemento y solo lo oculta con CSS) — así que
 * ni siquiera un bug futuro en un componente hijo puede volver a mostrar
 * dos estados terminales a la vez.
 */
class RucBackupMultipartUiStateTest extends TestCase
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

    private function pageContent(): string
    {
        return $this->actingAs($this->adminUser())
            ->get(route('admin.ruc.backups'))
            ->getContent();
    }

    public function test_every_multipart_stage_is_wrapped_in_its_own_template_x_if(): void
    {
        $content = $this->pageContent();

        foreach (['idle', 'manifest_selected', 'ready', 'uploading', 'assembling', 'completed', 'failed', 'cancelled'] as $stage) {
            $this->assertStringContainsString(
                "<template x-if=\"stage === '{$stage}'\">",
                $content,
                "El estado \"{$stage}\" debe estar envuelto en su propio <template x-if>, no en un x-show suelto."
            );
        }
    }

    public function test_terminal_state_alerts_rely_on_template_x_if_not_x_show(): void
    {
        // <x-ui.alert ...> se compila y desaparece del HTML renderizado
        // (queda reemplazado por su <div> real), así que este patrón solo
        // puede verificarse contra el ORIGEN Blade, no contra la respuesta
        // HTTP ya compilada.
        //
        // Nota: x-ui.alert SÍ admite x-show="..." directamente ahora (se
        // combina de forma segura con su "visible" interno — ver
        // DesignSystemComponentsTest) y eso es válido para alerts sueltos
        // como los de validación de manifest/partes. Lo que específicamente
        // NO debe reaparecer es el bug original: los tres estados
        // TERMINALES (completed/failed/cancelled) dependiendo de x-show en
        // vez de <template x-if> para su exclusión mutua.
        $source = file_get_contents(resource_path('views/ruc/admin/backups/index.blade.php'));

        $this->assertDoesNotMatchRegularExpression(
            "/<x-ui\\.alert[^>]*x-show=\"stage === '(completed|failed|cancelled)'\"/",
            $source,
            'Los estados terminales deben depender de <template x-if>, no de x-show en el propio <x-ui.alert>.'
        );
    }

    public function test_completed_failed_and_cancelled_markup_all_exist_exactly_once(): void
    {
        $content = $this->pageContent();

        $this->assertSame(1, substr_count($content, 'Backup importado correctamente'));
        $this->assertSame(1, substr_count($content, 'Error al importar el backup'));
        $this->assertSame(1, substr_count($content, 'Importación cancelada'));
    }

    public function test_page_never_uses_fetch_for_the_multipart_flow(): void
    {
        // La subida multipart SÍ usa JavaScript (XMLHttpRequest, en el
        // módulo dedicado) — a diferencia de restore/delete, esto es
        // intencional (cada parte debe ser un request independiente). Lo
        // que nunca debe aparecer es fetch().
        $content = $this->pageContent();

        $this->assertStringNotContainsString('fetch(', $content);
    }

    public function test_multipart_panel_has_aria_live_for_stage_changes(): void
    {
        $content = $this->pageContent();

        $this->assertStringContainsString('aria-live="polite"', $content);
    }
}
