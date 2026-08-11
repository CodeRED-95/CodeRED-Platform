<?php

namespace RucTool\Tests;

use PHPUnit\Framework\TestCase;
use RucTool\Services\PadronParser;

class PadronParserTest extends TestCase
{
    private PadronParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PadronParser;
    }

    public function test_valid_line_parses_correctly()
    {
        $line = '20123456789|EMPRESA DEMO S.A.C.|ACTIVO|HABIDO|150101|AV|LOS PROCERES|-|-|123|-|-|-|-|-';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('20123456789', $result['data']['ruc']);
        $this->assertSame('EMPRESA DEMO S.A.C.', $result['data']['razon_social']);
        $this->assertSame('ACTIVO', $result['data']['estado']);
        $this->assertSame('HABIDO', $result['data']['condicion']);
        $this->assertSame('150101', $result['data']['ubigeo']);
        $this->assertSame('AV LOS PROCERES 123', $result['data']['direccion']);
    }

    public function test_header_line_is_detected()
    {
        $line = 'RUC|NOMBRE O RAZÓN SOCIAL|ESTADO|CONDICION|UBIGEO';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('header', $result);
    }

    public function test_invalid_ruc_is_rejected()
    {
        $line = '2012345678|EMPRESA DEMO S.A.C.|ACTIVO|HABIDO|150101';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_empty_razon_social_is_rejected()
    {
        $line = '20123456789||ACTIVO|HABIDO|150101';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_malformed_ubigeo_becomes_null()
    {
        $line = '20123456789|EMPRESA DEMO S.A.C.|ACTIVO|HABIDO|ABC123';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('data', $result);
        $this->assertNull($result['data']['ubigeo']);
    }

    public function test_dash_placeholders_are_treated_as_empty()
    {
        $line = '20123456789|EMPRESA DEMO S.A.C.|-|-|-';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('data', $result);
        $this->assertNull($result['data']['estado']);
        $this->assertNull($result['data']['condicion']);
        $this->assertNull($result['data']['ubigeo']);
    }

    public function test_build_address_filters_placeholders()
    {
        $address = $this->parser->buildAddress(['AV', 'LOS PROCERES', '-', 'N/A', '123']);

        $this->assertSame('AV LOS PROCERES 123', $address);
    }

    public function test_build_address_returns_null_when_empty()
    {
        $address = $this->parser->buildAddress(['-', '', 'NULL']);

        $this->assertNull($address);
    }
}
