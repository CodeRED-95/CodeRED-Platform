<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PremiumDesignSystemTest extends TestCase
{
    #[Test]
    public function global_tokens_scrollbars_tables_and_reduced_motion_are_centralized(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);
        foreach (['--color-bg-app', '--shadow-sm', '--shadow-md', '--radius-xl', '--transition-fast', 'scrollbar-color', '.ui-table', 'prefers-reduced-motion'] as $contract) {
            $this->assertStringContainsString($contract, $css);
        }
    }

    #[Test]
    public function shell_has_independent_scroll_and_one_consistent_content_width(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertIsString($layout);
        $this->assertStringContainsString('h-dvh overflow-hidden', $layout);
        $this->assertStringContainsString('id="main-content"', $layout);
        $this->assertStringContainsString('max-w-[1680px]', $layout);
        $this->assertStringContainsString('aria-current="page"', $layout);
    }
}
