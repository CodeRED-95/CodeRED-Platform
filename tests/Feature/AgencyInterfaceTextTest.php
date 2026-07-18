<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgencyInterfaceTextTest extends TestCase
{
    public function test_admin_view_contains_centro_de_operaciones_label(): void
    {
        $html = file_get_contents(resource_path('views/livewire/admin/agencies/index.blade.php'));

        $this->assertIsString($html);
        $this->assertStringContainsString('Centro de Operaciones', $html);
    }
}
