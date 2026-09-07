<?php

declare(strict_types=1);

namespace Tests\Unit\Sunat;

use App\Services\Sunat\SunatRepresentativeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SunatRepresentativeClientTest extends TestCase
{
    public function test_ejecuta_flujo_http_de_tres_pasos_con_la_misma_sesion(): void
    {
        config(['sunat.representatives.url' => 'https://sunat.test/cl-ti-itmrconsruc/FrameCriterioBusquedaWeb.jsp']);

        Http::fakeSequence()
            ->push('<form name="mainForm" action="jcrS00Alias"></form>')
            ->push('<form name="formRepLeg" action="/cl-ti-itmrconsruc/jcrS00Alias"><input name="contexto" value="ti-it"><input name="modo" value="1"><input name="desRuc" value="EMPRESA TEST"></form>')
            ->push('<table><tr><th>Documento</th><th>Nro. Documento</th><th>Nombre</th><th>Cargo</th><th>Fecha Desde</th></tr></table>');

        $html = app(SunatRepresentativeClient::class)->fetch('20512528458');

        $this->assertStringContainsString('<table>', $html);
        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->data()['accion'] === 'consPorRuc'
                && $request->data()['nroRuc'] === '20512528458'
                && strlen((string) $request->data()['token']) === 52;
        });
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->data()['accion'] === 'getRepLeg'
                && $request->data()['desRuc'] === 'EMPRESA TEST'
                && in_array('https://sunat.test/cl-ti-itmrconsruc/jcrS00Alias', (array) $request->header('Referer'), true);
        });
    }
}
