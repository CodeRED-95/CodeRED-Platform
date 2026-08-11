<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BuscadorShalomLegalPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function privacy_page_is_accessible_without_authentication()
    {
        $response = $this->get(route('public.buscador-shalom.privacy'));

        $response->assertStatus(200);
        $response->assertSee('Política de Privacidad', false);
        $response->assertSee(config('codered.support_email'));
    }

    #[Test]
    public function support_page_is_accessible_without_authentication()
    {
        $response = $this->get(route('public.buscador-shalom.support'));

        $response->assertStatus(200);
        $response->assertSee('Página de Soporte', false);
        $response->assertSee(config('codered.support_email'));
        $response->assertSee(route('public.buscador-shalom.privacy'));
    }

    #[Test]
    public function privacy_page_contains_required_content()
    {
        $response = $this->get(route('public.buscador-shalom.privacy'));

        $response->assertSee('Buscador Shalom');
        $response->assertSee('Política de Privacidad');
        $response->assertSee('almacenamiento local');
        $response->assertSee('tokens');
        $response->assertSee(config('codered.legal_name'));
        $response->assertSee(config('codered.privacy_updated_at'));
    }

    #[Test]
    public function support_page_contains_required_content()
    {
        $response = $this->get(route('public.buscador-shalom.support'));

        $response->assertSee('Buscador Shalom');
        $response->assertSee('Soporte');
        $response->assertSee(config('codered.support_email'));
        $response->assertSee('Token inválido o vencido');
        $response->assertSee('Nunca comparta sus tokens de API completos');
    }

    #[Test]
    public function pages_do_not_expose_sensitive_information()
    {
        $response = $this->get(route('public.buscador-shalom.privacy'));
        $response->assertDontSee('APP_KEY');
        $response->assertDontSee('DB_PASSWORD');

        $response = $this->get(route('public.buscador-shalom.support'));
        $response->assertDontSee('APP_KEY');
        $response->assertDontSee('DB_PASSWORD');
    }

    #[Test]
    public function canonical_urls_are_present()
    {
        $response = $this->get(route('public.buscador-shalom.privacy'));
        $response->assertSee('<link rel="canonical"');

        $response = $this->get(route('public.buscador-shalom.support'));
        $response->assertSee('<link rel="canonical"');
    }
}
