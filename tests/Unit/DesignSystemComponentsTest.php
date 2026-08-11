<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignSystemComponentsTest extends TestCase
{
    /**
     * Regresión directa del bug reportado en /admin/ruc/backups: x-ui.alert
     * tenía x-show="visible" fijo en su raíz. Un caller externo pasando su
     * propio x-show (p. ej. dentro de una máquina de estados) generaba DOS
     * atributos x-show duplicados en el mismo <div> — HTML colapsa
     * duplicados al primero, así que el x-show externo quedaba
     * silenciosamente ignorado y el alert se mostraba siempre, sin importar
     * el estado real (los tres estados terminales visibles a la vez).
     */
    #[Test]
    public function alert_combines_external_x_show_with_internal_visible_without_duplicating_the_attribute(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.alert tone="danger" x-show="stage === 'failed'">Error</x-ui.alert>
        BLADE);

        // Debe existir EXACTAMENTE un atributo x-show en todo el HTML.
        $this->assertSame(1, substr_count($html, 'x-show='));
        $this->assertStringContainsString('stage === &#039;failed&#039;', $html);
        $this->assertStringContainsString('&amp;&amp; visible', $html);
    }

    #[Test]
    public function alert_without_external_x_show_keeps_default_dismiss_behavior(): void
    {
        $html = Blade::render('<x-ui.alert tone="info">Mensaje</x-ui.alert>');

        $this->assertSame(1, substr_count($html, 'x-show='));
        $this->assertStringContainsString('x-show="visible"', $html);
    }

    #[Test]
    public function alert_supports_title_description_and_actions_slot(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.alert tone="success" title="Backup importado correctamente">
                ruc_backup_2026-08-08.dump
                <x-slot:actions>
                    <x-ui.button size="sm">Ver en la lista</x-ui.button>
                </x-slot:actions>
            </x-ui.alert>
        BLADE);

        $this->assertStringContainsString('Backup importado correctamente', $html);
        $this->assertStringContainsString('ruc_backup_2026-08-08.dump', $html);
        $this->assertStringContainsString('Ver en la lista', $html);
    }

    #[Test]
    public function alert_supports_all_six_design_system_tones(): void
    {
        foreach (['neutral', 'brand', 'info', 'success', 'warning', 'danger'] as $tone) {
            $html = Blade::render('<x-ui.alert tone="'.$tone.'">Mensaje</x-ui.alert>');
            $this->assertStringContainsString('<svg', $html, "tone {$tone} debe renderizar un ícono real, no un glifo de texto");
        }
    }

    #[Test]
    public function file_dropzone_supports_multiple_files_and_on_select_hook(): void
    {
        $html = Blade::render(
            '<x-ui.file-dropzone name="parts" label="Partes" multiple on-select="onPartsSelected($event)" />'
        );

        $this->assertStringContainsString('multiple', $html);
        $this->assertStringContainsString('onPartsSelected($event)', $html);
        // El hook se compone CON setFiles, nunca lo reemplaza (si no, se
        // pierde el preview visual del dropzone).
        $this->assertStringContainsString('setFiles($event.target.files); onPartsSelected($event)', $html);
    }

    #[Test]
    public function input_supports_prefix_suffix_and_error_state(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.input label="Clave" error="Campo inválido">
                <x-slot:prefix><span data-prefix>+</span></x-slot:prefix>
                <x-slot:suffix><button type="button" data-suffix>Ver</button></x-slot:suffix>
            </x-ui.input>
        BLADE);

        $this->assertStringContainsString('data-prefix', $html);
        $this->assertStringContainsString('data-suffix', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('Campo inválido', $html);
    }

    #[Test]
    public function search_box_forwards_livewire_model_and_renders_search_icon(): void
    {
        $html = Blade::render('<x-ui.search-box wire:model.live="search" label="Buscar usuarios" />');

        $this->assertStringContainsString('type="search"', $html);
        $this->assertStringContainsString('wire:model.live="search"', $html);
        $this->assertStringContainsString('Buscar usuarios', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    #[Test]
    public function confirm_dialog_exposes_accessible_contract_and_livewire_action(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.confirm-dialog
                id="delete-record"
                title="Eliminar registro"
                message="Esta acción no se puede deshacer."
                confirm-action="deleteRecord"
            >
                <x-slot:trigger><x-ui.button>Eliminar</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>
        BLADE);

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="delete-record-title"', $html);
        $this->assertStringContainsString('wire:click="deleteRecord"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window', $html);
        $this->assertStringContainsString('x-on:keydown.tab="trapFocus($event)"', $html);
    }

    #[Test]
    public function confirm_dialog_supports_traditional_form_submit_mode(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.confirm-dialog
                id="restore-dialog-1"
                title="Restaurar backup"
                tone="warning"
                icon="restore"
                confirm-label="Restaurar Backup"
                form="restore-form-1"
                loading-label="Iniciando restauración…"
            >
                <x-slot:trigger><x-ui.button>Restaurar</x-ui.button></x-slot:trigger>
                <p>Esta acción reemplazará los datos actuales.</p>
            </x-ui.confirm-dialog>
        BLADE);

        // Modo formulario: sin wire:click, con requestSubmit sobre un <form> real.
        $this->assertStringNotContainsString('wire:click', $html);
        $this->assertStringContainsString('document.getElementById(', $html);
        $this->assertStringContainsString('requestSubmit', $html);
        $this->assertStringContainsString('restore-form-1', $html);
        $this->assertStringContainsString('Esta acción reemplazará los datos actuales.', $html);
        $this->assertStringContainsString('Iniciando restauración…', $html);
        // Evita doble submit: ambos botones se deshabilitan mientras submitting=true.
        $this->assertStringContainsString('x-bind:disabled="submitting"', $html);
    }

    #[Test]
    public function confirm_dialog_tones_resolve_to_distinct_icon_and_button_variant(): void
    {
        $warning = Blade::render('<x-ui.confirm-dialog id="t-warning" title="T" tone="warning" message="m"><x-slot:trigger>x</x-slot:trigger></x-ui.confirm-dialog>');
        $danger = Blade::render('<x-ui.confirm-dialog id="t-danger" title="T" tone="danger" message="m"><x-slot:trigger>x</x-slot:trigger></x-ui.confirm-dialog>');
        $info = Blade::render('<x-ui.confirm-dialog id="t-info" title="T" tone="info" message="m"><x-slot:trigger>x</x-slot:trigger></x-ui.confirm-dialog>');

        $this->assertStringContainsString('bg-amber-500/10', $warning);
        $this->assertStringContainsString('bg-rose-500/10', $danger);
        $this->assertStringContainsString('bg-sky-500/10', $info);
    }

    #[Test]
    public function file_dropzone_renders_native_input_with_expected_attributes(): void
    {
        $html = Blade::render(
            '<x-ui.file-dropzone name="backup" label="Archivo de Backup" accept=".dump,.gz" required help="Solo .dump o .gz" max-size="5000 MB" />'
        );

        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('name="backup"', $html);
        $this->assertStringContainsString('accept=".dump,.gz"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('Solo .dump o .gz', $html);
        $this->assertStringContainsString('5000 MB', $html);
        // Solo UX: nunca debe intentar subir el archivo por su cuenta.
        $this->assertStringNotContainsString('fetch(', $html);
        $this->assertStringNotContainsString('XMLHttpRequest', $html);
    }

    #[Test]
    public function progress_renders_determinate_and_indeterminate_states(): void
    {
        $determinate = Blade::render('<x-ui.progress :value="50" :max="100" label="Restaurando" />');
        $indeterminate = Blade::render('<x-ui.progress indeterminate label="Restaurando" />');

        $this->assertStringContainsString('role="progressbar"', $determinate);
        $this->assertStringContainsString('aria-valuemin="0"', $determinate);
        $this->assertStringContainsString('aria-valuemax="100"', $determinate);
        $this->assertStringContainsString('aria-valuenow="50"', $determinate);
        $this->assertStringContainsString('50%', $determinate);

        // Progreso indeterminado: nunca debe fingir un aria-valuenow ni un porcentaje.
        $this->assertStringNotContainsString('aria-valuenow', $indeterminate);
        $this->assertStringContainsString('aria-label="Restaurando"', $indeterminate);
    }

    #[Test]
    public function process_steps_renders_completed_active_pending_and_failed(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.process-steps :steps="[
                ['label' => 'Backup validado', 'status' => 'completed'],
                ['label' => 'Restaurando datos', 'status' => 'active'],
                ['label' => 'Verificando', 'status' => 'pending'],
                ['label' => 'Restauración fallida', 'status' => 'failed', 'description' => 'rollback automático'],
            ]" />
        BLADE);

        $this->assertStringContainsString('Backup validado', $html);
        $this->assertStringContainsString('(completado)', $html);
        $this->assertStringContainsString('Restaurando datos', $html);
        $this->assertStringContainsString('(en curso)', $html);
        $this->assertStringContainsString('Verificando', $html);
        $this->assertStringContainsString('(pendiente)', $html);
        $this->assertStringContainsString('Restauración fallida', $html);
        $this->assertStringContainsString('(falló)', $html);
        $this->assertStringContainsString('rollback automático', $html);
    }

    #[Test]
    public function spinner_and_skeleton_expose_accessible_loading_states(): void
    {
        $spinner = Blade::render('<x-ui.spinner size="sm" label="Guardando" />');
        $skeleton = Blade::render('<x-ui.skeleton variant="table" :rows="2" />');

        $this->assertStringContainsString('role="status"', $spinner);
        $this->assertStringContainsString('Guardando', $spinner);
        $this->assertStringContainsString('aria-label="Cargando contenido"', $skeleton);
        $this->assertStringContainsString('animate-pulse', $skeleton);
    }

    #[Test]
    public function toast_stack_accepts_initial_messages_and_browser_events(): void
    {
        $html = Blade::render(
            '<x-ui.toast-stack :messages="[[\'tone\' => \'success\', \'message\' => \'Guardado correctamente\']]" />'
        );

        $this->assertStringContainsString('x-on:toast.window', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('Guardado correctamente', $html);
        $this->assertStringContainsString('Cerrar notificación', $html);
        $this->assertStringContainsString('toast.paused', $html);
    }
}
