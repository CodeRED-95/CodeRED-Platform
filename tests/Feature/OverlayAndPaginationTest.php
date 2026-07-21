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
        $this->assertStringContainsString('Mostrando 1 a 15 de 32 registros', $html);
        $this->assertStringContainsString('bg-[color:var(--color-background-elevated)]', $html);
        $this->assertStringContainsString('bg-[color:var(--color-brand)]', $html);
        $this->assertStringContainsString('hover:bg-[color:var(--color-surface-hover)]', $html);
        $this->assertStringContainsString('cursor-not-allowed', $html);
        $this->assertStringContainsString('focus-ring', $html);
        $this->assertStringNotContainsString('bg-white', $html);
        $this->assertStringNotContainsString('Showing', $html);
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_pagination_covers_first_middle_last_single_and_empty_pages(): void
    {
        foreach ([1 => 'Mostrando 1 a 10 de 25 registros', 2 => 'Mostrando 11 a 20 de 25 registros', 3 => 'Mostrando 21 a 25 de 25 registros'] as $page => $summary) {
            $items = range((($page - 1) * 10) + 1, min($page * 10, 25));
            $paginator = new LengthAwarePaginator($items, 25, 10, $page, ['path' => '/admin/ruc']);
            $html = Blade::render('<x-ui.pagination :paginator="$paginator" />', compact('paginator'));

            $this->assertStringContainsString($summary, $html);
            $this->assertStringContainsString('aria-current="page"', $html);
            $this->assertStringContainsString('Página '.$page.', actual', $html);
        }

        foreach ([new LengthAwarePaginator([1], 1, 25, 1), new LengthAwarePaginator([], 0, 25, 1)] as $paginator) {
            $this->assertSame('', trim(Blade::render('<x-ui.pagination :paginator="$paginator" />', compact('paginator'))));
        }
    }

    public function test_all_paginated_admin_views_use_the_shared_component(): void
    {
        foreach (['livewire/admin/ruc/records.blade.php', 'livewire/admin/ruc/imports.blade.php', 'livewire/admin/agencies/backups.blade.php'] as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));
            $this->assertStringContainsString('x-ui.pagination', $contents);
            $this->assertStringNotContainsString('->links()', $contents);
        }
    }
}
