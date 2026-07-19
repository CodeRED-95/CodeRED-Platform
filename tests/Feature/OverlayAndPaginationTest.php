<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class OverlayAndPaginationTest extends TestCase
{
    public function test_dropdown_keeps_scroll_inside_panel_and_uses_non_submitting_buttons(): void
    {
        $options = ['active' => 'Activa', 'inactive' => 'Inactiva'];
        $html = Blade::render(
            '<x-ui.dropdown-select id="status-test" name="status" label="Estado" value="active" :options="$options" />',
            compact('options'),
        );

        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('keepActiveOptionVisible', $html);
        $this->assertStringContainsString('focus({ preventScroll: true })', file_get_contents(resource_path('js/app.js')));
        $this->assertStringNotContainsString('scrollIntoView', $html);
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_pagination_uses_dark_accessible_view_with_active_and_disabled_states(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 15), 32, 15, 1, [
            'path' => '/admin/agencies',
            'pageName' => 'page',
        ]);

        $html = Blade::render('<x-ui.pagination :paginator="$paginator" scroll-to="#agencies-list" />', compact('paginator'));

        $this->assertStringContainsString('aria-label="Paginación"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('Ir a la página 2', $html);
        $this->assertStringContainsString('Mostrando', $html);
        $this->assertStringContainsString('bg-[color:var(--color-background-elevated)]', $html);
        $this->assertStringNotContainsString('bg-white', $html);
        $this->assertStringNotContainsString('href="#"', $html);
    }
}
