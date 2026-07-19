<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignSystemComponentsTest extends TestCase
{
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
    }
}
