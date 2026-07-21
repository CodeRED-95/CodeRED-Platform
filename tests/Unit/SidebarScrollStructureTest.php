<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SidebarScrollStructureTest extends TestCase
{
    #[Test]
    public function desktop_and_mobile_sidebars_have_independent_scroll_regions(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertIsString($layout);

        $this->assertStringContainsString('h-dvh min-h-0 overflow-hidden', $layout);
        $this->assertGreaterThanOrEqual(2, substr_count($layout, 'data-sidebar-navigation'));
        $this->assertGreaterThanOrEqual(2, substr_count($layout, 'min-h-0 flex-1'));
        $this->assertGreaterThanOrEqual(3, substr_count($layout, 'overflow-y-auto'));
        $this->assertStringContainsString('data-sidebar-active="true"', $layout);
        $this->assertStringContainsString("scrollIntoView({ block: 'nearest'", $layout);
        $this->assertStringContainsString('overscroll-contain', $layout);
    }
}
