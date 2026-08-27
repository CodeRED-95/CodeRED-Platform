<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShalomRecordarLegalPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function privacy_page_is_accessible_without_authentication()
    {
        $response = $this->get(route('public.registro-actividad-shalom.privacy'));

        $response->assertStatus(200);
        $response->assertSee('Política de Privacidad', false);
        $response->assertSee(config('codered.support_email'));
    }

    #[Test]
    public function support_page_is_accessible_without_authentication()
    {
        $response = $this->get(route('public.registro-actividad-shalom.support'));

        $response->assertStatus(200);
        $response->assertSee('Página de Soporte', false);
        $response->assertSee(config('codered.support_email'));
        $response->assertSee(route('public.registro-actividad-shalom.privacy'));
    }

    #[Test]
    public function privacy_page_contains_required_content()
    {
        $response = $this->get(route('public.registro-actividad-shalom.privacy'));

        $response->assertSee('Registro de Actividad Shalom', false);
        $response->assertSee('Política de Privacidad', false);
        $response->assertSee('AES-256-GCM');
        $response->assertSee('contraseña nunca se guarda', false);
        $response->assertSee(config('codered.legal_name'));
        $response->assertSee(config('codered.registro_actividad_privacy_updated_at'));
    }

    #[Test]
    public function support_page_contains_required_content()
    {
        $response = $this->get(route('public.registro-actividad-shalom.support'));

        $response->assertSee('Registro de Actividad Shalom', false);
        $response->assertSee('Soporte');
        $response->assertSee('Token inválido o vencido', false);
        $response->assertSee('Nunca comparta sus tokens de API completos', false);
    }

    #[Test]
    public function pages_do_not_expose_sensitive_information()
    {
        $response = $this->get(route('public.registro-actividad-shalom.privacy'));
        $response->assertDontSee('APP_KEY');
        $response->assertDontSee('DB_PASSWORD');

        $response = $this->get(route('public.registro-actividad-shalom.support'));
        $response->assertDontSee('APP_KEY');
        $response->assertDontSee('DB_PASSWORD');
    }

    #[Test]
    public function canonical_urls_are_present()
    {
        $response = $this->get(route('public.registro-actividad-shalom.privacy'));
        $response->assertSee('<link rel="canonical"');

        $response = $this->get(route('public.registro-actividad-shalom.support'));
        $response->assertSee('<link rel="canonical"');
    }
}
