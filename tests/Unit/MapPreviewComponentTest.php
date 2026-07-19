<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MapPreviewComponentTest extends TestCase
{
    #[Test]
    public function map_preview_renders_openstreetmap_and_codered_marker_for_valid_coordinates(): void
    {
        $html = Blade::render('<x-ui.map-preview latitude="-12.046374" longitude="-77.042793" label="Ubicación de Agencia Lima" />');

        $this->assertStringContainsString('https://www.openstreetmap.org/export/embed.html?', $html);
        $this->assertStringContainsString('title="Ubicación de Agencia Lima"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('referrerpolicy="no-referrer"', $html);
        $this->assertStringContainsString('codered-symbol.png', $html);
        $this->assertStringContainsString('-12.046374', $html);
        $this->assertStringContainsString('-77.042793', $html);
    }

    #[Test]
    public function map_preview_exposes_an_accessible_fallback_without_coordinates(): void
    {
        $html = Blade::render('<x-ui.map-preview />');

        $this->assertStringContainsString('No hay coordenadas válidas para mostrar el mapa.', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    #[Test]
    public function floating_components_follow_the_documented_layer_scale(): void
    {
        $this->assertStringContainsString('z-50', File::get(resource_path('views/components/ui/dropdown.blade.php')));
        $this->assertStringContainsString('z-[60]', File::get(resource_path('views/components/ui/modal.blade.php')));
        $this->assertStringContainsString('z-[60]', File::get(resource_path('views/components/ui/confirm-dialog.blade.php')));
        $this->assertStringContainsString('z-[80]', File::get(resource_path('views/components/ui/toast-stack.blade.php')));
    }
}
