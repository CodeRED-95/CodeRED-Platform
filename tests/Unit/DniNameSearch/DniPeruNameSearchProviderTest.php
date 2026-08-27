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
}
