<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignSystemConsistencyTest extends TestCase
{
    #[Test]
    public function blade_views_do_not_use_legacy_light_form_styles_or_inline_javascript(): void
    {
        $forbiddenPatterns = [
            'border-slate-200',
            'dark:border-slate-',
            'dark:bg-slate-',
            'text-red-500',
            'onclick=',
            'x-ui.alert variant=',
        ];

        foreach (File::allFiles(resource_path('views')) as $view) {
            if ($view->getExtension() !== 'php') {
                continue;
            }

            $contents = mb_strtolower($view->getContents());

            foreach ($forbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $contents,
                    $view->getRelativePathname()." contiene el patrón visual heredado: $pattern"
                );
            }
        }
    }
}
