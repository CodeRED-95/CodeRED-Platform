<?php

declare(strict_types=1);

namespace Tests\Unit\DniNameSearch;

use App\Services\DniNameSearch\DniPeruNameSearchProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DniPeruNameSearchProviderTest extends TestCase
{
    public function test_discovers_public_form_and_parses_table_results(): void
    {
        config([
            'dni.name_search.providers.dniperu.enabled' => true,
            'dni.name_search.providers.dniperu.url' => 'https://example.test/buscar-dni-por-nombre/',
            'dni.name_search.providers.dniperu.retries' => 0,
        ]);

        $html = <<<'HTML'
<html><body>
<form method="post" action="/buscar-dni-por-nombre/">
<label for="nombres">Nombres</label><input id="nombres" name="nombres" type="text">
<label for="apellido_paterno">Apellido paterno</label><input id="apellido_paterno" name="apellido_paterno" type="text">
<label for="apellido_materno">Apellido materno</label><input id="apellido_materno" name="apellido_materno" type="text">
<input type="submit" value="Consultar">
</form>
<table><tr><td>12345678</td><td>JUAN</td><td>PEREZ</td><td>GOMEZ</td></tr></table>
</body></html>
HTML;

        Http::fakeSequence()->push($html, 200, ['Content-Type' => 'text/html'])->push($html, 200, ['Content-Type' => 'text/html']);
        $result = (new DniPeruNameSearchProvider)->search('JUAN', 'PEREZ', 'GOMEZ');

        self::assertSame('found', $result->status);
        self::assertSame('12345678', $result->matches[0]->dni);
        self::assertSame('JUAN', $result->matches[0]->nombres);
    }

    public function test_uses_ajax_token_flow_when_the_public_form_requires_it(): void
    {
        config([
            'dni.name_search.providers.dniperu.enabled' => true,
            'dni.name_search.providers.dniperu.url' => 'https://example.test/buscar-dni-por-nombre/',
            'dni.name_search.providers.dniperu.retries' => 0,
        ]);

        $html = <<<'HTML'
<html><body>
<div class="cc-consulta cc-consulta--dni-nombre cc-dni-block">
    <form class="js-cc-dni-form" method="post" action="">
        <input name="nombres" type="text">
        <input name="apellido_paterno" type="text">
        <input name="apellido_materno" type="text">
        <input type="hidden" name="company" value="">
        <button type="submit">Consultar</button>
    </form>
</div>
</body></html>
HTML;

        $tokenPayload = [
            'success' => true,
            'data' => [
                'cc_token' => 'token-123',
                'cc_sig' => 'sig-456',
            ],
        ];

        $searchPayload = [
            'success' => true,
            'data' => [
                'resultados' => [
                    [
                        'numero' => '12345678',
                        'nombres' => 'JUAN CARLOS',
                        'apellido_paterno' => 'PEREZ',
                        'apellido_materno' => 'GOMEZ',
                    ],
                ],
            ],
        ];

        Http::fake([
            'example.test/buscar-dni-por-nombre/' => Http::response($html, 200, ['Content-Type' => 'text/html']),
            'example.test/wp-admin/admin-ajax.php' => Http::sequence()
                ->push($tokenPayload, 200)
                ->push($searchPayload, 200),
        ]);

        $result = (new DniPeruNameSearchProvider)->search('JUAN CARLOS', 'PEREZ', 'GOMEZ');

        self::assertSame('found', $result->status);
        self::assertSame('12345678', $result->matches[0]->dni);
        self::assertSame('JUAN CARLOS', $result->matches[0]->nombres);
    }
}
