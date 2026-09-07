<?php

declare(strict_types=1);

namespace Tests\Unit\Sunat;

use App\Services\Sunat\SunatRepresentativeParser;
use App\Exceptions\SunatRepresentativeException;
use Tests\TestCase;

class SunatRepresentativeParserTest extends TestCase
{
    public function test_parsea_un_representante_y_normaliza_texto_y_fecha(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ruc/sunat-representatives-sample.html'));
        $data = (new SunatRepresentativeParser())->parse($html);

        $this->assertCount(2, $data);
        $this->assertSame('DNI', $data[0]->tipoDocumento);
        $this->assertSame('12345678', $data[0]->numeroDocumento);
        $this->assertSame('APELLIDOS NOMBRES', $data[0]->nombre);
        $this->assertSame('GERENTE GENERAL', $data[0]->cargo);
        $this->assertSame('2020-01-15', $data[0]->fechaDesde);
    }

    public function test_html_sin_tablas_lanza_excepcion(): void
    {
        $this->expectException(SunatRepresentativeException::class);

        (new SunatRepresentativeParser())->parse('<html><body>sin tablas</body></html>');
    }

    public function test_parsea_fixture_real_de_sunat_con_multiples_representantes(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ruc/sunat-representatives-real.html'));
        $data = (new SunatRepresentativeParser())->parse($html);

        $this->assertCount(2, $data);
        $this->assertSame([
            'tipo_documento',
            'numero_documento',
            'nombre',
            'cargo',
            'fecha_desde',
        ], array_keys($data[0]->toArray()));
        $this->assertSame('2006-02-03', $data[0]->fechaDesde);
        $this->assertSame('2015-06-23', $data[1]->fechaDesde);
    }
}
