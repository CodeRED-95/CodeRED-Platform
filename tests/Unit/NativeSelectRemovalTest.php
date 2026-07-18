<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NativeSelectRemovalTest extends TestCase
{
    #[Test]
    public function blade_views_do_not_contain_native_select_controls(): void
    {
        foreach (File::allFiles(resource_path('views')) as $view) {
            if ($view->getExtension() !== 'php') {
                continue;
            }

            $contents = mb_strtolower($view->getContents());
            $relativePath = $view->getRelativePathname();

            $this->assertStringNotContainsString('<select', $contents, "$relativePath contiene un select nativo.");
            $this->assertStringNotContainsString('<option', $contents, "$relativePath contiene una option nativa.");
        }
    }
}
