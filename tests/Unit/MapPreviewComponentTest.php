<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MapPreviewComponentTest extends TestCase
{
    #[Test]
    public function map_preview_renders_leaflet_contract_and_codered_marker_for_valid_coordinates(): void
    {
        $html = Blade::render('<x-ui.map-preview latitude="-12.046374" longitude="-77.042793" name="Agencia Lima" location="Lima / Lima / Cercado" label="Ubicación de Agencia Lima" />');

        $this->assertStringContainsString('x-data="codeRedMap(', $html);
        $this->assertStringContainsString('data-codered-map', $html);
        $this->assertStringContainsString('wire:ignore', $html);
        $this->assertStringContainsString('aria-label="Ubicación de Agencia Lima"', $html);
        $this->assertStringContainsString('codered-symbol.png', $html);
        $this->assertStringContainsString('-12.046374', $html);
        $this->assertStringContainsString('-77.042793', $html);
        $this->assertStringContainsString('lg:h-[340px]', $html);
    }

    #[Test]
    public function map_preview_exposes_an_accessible_fallback_without_coordinates(): void
    {
        $html = Blade::render('<x-ui.map-preview />');

        $this->assertStringContainsString('Esta agencia todavía no tiene coordenadas registradas.', $html);
        $this->assertStringNotContainsString('data-codered-map', $html);
    }

    #[Test]
    public function leaflet_initializer_handles_tiles_marker_popup_resize_and_livewire_navigation(): void
    {
        $javascript = File::get(resource_path('js/app.js'));

        $this->assertMatchesRegularExpression('/import L from [\"\']leaflet[\"\']/', $javascript);
        $this->assertMatchesRegularExpression('/import [\"\']leaflet\/dist\/leaflet\.css[\"\']/', $javascript);
        $this->assertStringContainsString('tile.openstreetmap.org', $javascript);
        $this->assertStringContainsString('codered-map-marker', $javascript);
        $this->assertStringContainsString('bindPopup', $javascript);
        $this->assertStringContainsString('invalidateSize', $javascript);
        $this->assertStringContainsString('livewire:navigating', $javascript);
        $this->assertStringContainsString('this.map?.remove()', $javascript);
    }

    #[Test]
    public function floating_components_follow_the_documented_layer_scale(): void
    {
        $styles = File::get(resource_path('css/app.css'));
        $dropdown = File::get(resource_path('views/components/ui/dropdown.blade.php'));
        $modal = File::get(resource_path('views/components/ui/modal.blade.php'));
        $confirmation = File::get(resource_path('views/components/ui/confirm-dialog.blade.php'));
        $toasts = File::get(resource_path('views/components/ui/toast-stack.blade.php'));

        $this->assertStringContainsString('--layer-popover: 50', $styles);
        $this->assertStringContainsString('--layer-modal: 70', $styles);
        $this->assertStringContainsString('--layer-toast: 80', $styles);
        $this->assertStringContainsString('layer-popover', $dropdown);
        $this->assertStringContainsString('x-teleport="body"', $dropdown);
        $this->assertStringContainsString('layer-modal', $modal);
        $this->assertStringContainsString('x-teleport="body"', $modal);
        $this->assertStringContainsString('layer-modal', $confirmation);
        $this->assertStringContainsString('x-teleport="body"', $confirmation);
        $this->assertStringContainsString('id="global-toast-region"', $toasts);
        $this->assertStringContainsString('layer-toast', $toasts);
    }
}
