<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgencyInterfaceTextTest extends TestCase
{
    public function test_admin_view_contains_centro_de_operaciones_label(): void
    {
        $html = view('livewire.admin.agencies.index', ['agencies' => collect()])->render();

        $this->assertStringContainsString('Centro de Operaciones', $html);
    }
}
