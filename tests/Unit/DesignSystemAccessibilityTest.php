<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignSystemAccessibilityTest extends TestCase
{
    #[Test]
    public function form_controls_associate_labels_required_state_and_errors(): void
    {
        $input = Blade::render('<x-ui.input id="email" label="Correo" required error="Correo inválido" />');
        $textarea = Blade::render('<x-ui.textarea id="notes" label="Notas" error="Notas inválidas" />');

        $this->assertStringContainsString('for="email"', $input);
        $this->assertStringContainsString('id="email-error"', $input);
        $this->assertStringContainsString('aria-describedby="email-error"', $input);
        $this->assertStringContainsString('aria-invalid="true"', $input);
        $this->assertStringContainsString('(obligatorio)', $input);
        $this->assertStringContainsString('for="notes"', $textarea);
        $this->assertStringContainsString('id="notes-error"', $textarea);
        $this->assertStringContainsString('aria-describedby="notes-error"', $textarea);
    }

    #[Test]
    public function dropdown_associates_error_without_changing_livewire_contract(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.dropdown-select
                id="status"
                wire:model.live="status"
                label="Estado"
                error="Selecciona un estado"
                :options="['active' => 'Activa']"
            />
        BLADE);

        $this->assertStringContainsString('aria-describedby="status-error"', $html);
        $this->assertStringContainsString('id="status-error"', $html);
        $this->assertStringContainsString('wire:model.live="status"', $html);
        $this->assertStringContainsString('Activa', $html);
    }

    #[Test]
    public function confirm_dialog_keeps_focus_trap_and_escape_guard_in_form_mode(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.confirm-dialog id="restore-2" title="Restaurar" tone="warning" form="restore-form-2" message="m">
                <x-slot:trigger><x-ui.button>Restaurar</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>
        BLADE);

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="restore-2-title"', $html);
        $this->assertStringContainsString('aria-describedby="restore-2-description"', $html);
        // Escape no debe cerrar el diálogo mientras se está enviando el formulario.
        $this->assertStringContainsString('if (open && !submitting) close()', $html);
        $this->assertStringContainsString('x-on:keydown.tab="trapFocus($event)"', $html);
    }

    #[Test]
    public function file_dropzone_associates_label_error_and_invalid_state(): void
    {
        $html = Blade::render('<x-ui.file-dropzone id="backup-file" name="backup" label="Archivo" error="Extensión inválida" />');

        $this->assertStringContainsString('for="backup-file"', $html);
        $this->assertStringContainsString('id="backup-file"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('Extensión inválida', $html);
    }

    #[Test]
    public function card_supports_optional_header_actions_and_legacy_slot(): void
    {
        $structured = Blade::render(<<<'BLADE'
            <x-ui.card title="Perfil" description="Datos de la cuenta">
                <x-slot:actions><button type="button">Editar</button></x-slot:actions>
                Contenido
            </x-ui.card>
        BLADE);
        $legacy = Blade::render('<x-ui.card>Contenido existente</x-ui.card>');

        $this->assertStringContainsString('Perfil', $structured);
        $this->assertStringContainsString('Datos de la cuenta', $structured);
        $this->assertStringContainsString('Editar', $structured);
        $this->assertStringContainsString('Contenido', $structured);
        $this->assertStringContainsString('Contenido existente', $legacy);
    }
}
