<?php

namespace RucTool\Tests;

use PHPUnit\Framework\TestCase;
use RucTool\Services\PadronParser;

class PadronParserTest extends TestCase
{
    private PadronParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PadronParser();
    }

    public function testValidLineParsesCorrectly()
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

    public function testHeaderLineIsDetected()
    {
        $line = 'RUC|NOMBRE O RAZÓN SOCIAL|ESTADO|CONDICION|UBIGEO';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('header', $result);
    }

    public function testInvalidRucIsRejected()
    {
        $line = '2012345678|EMPRESA DEMO S.A.C.|ACTIVO|HABIDO|150101';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('error', $result);
    }

    public function testEmptyRazonSocialIsRejected()
    {
        $line = '20123456789||ACTIVO|HABIDO|150101';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('error', $result);
    }

    public function testMalformedUbigeoBecomesNull()
    {
        $line = '20123456789|EMPRESA DEMO S.A.C.|ACTIVO|HABIDO|ABC123';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('data', $result);
        $this->assertNull($result['data']['ubigeo']);
    }

    public function testDashPlaceholdersAreTreatedAsEmpty()
    {
        $line = '20123456789|EMPRESA DEMO S.A.C.|-|-|-';

        $result = $this->parser->parse($line, '|', 'UTF-8');

        $this->assertArrayHasKey('data', $result);
        $this->assertNull($result['data']['estado']);
        $this->assertNull($result['data']['condicion']);
        $this->assertNull($result['data']['ubigeo']);
    }

    public function testBuildAddressFiltersPlaceholders()
    {
        $address = $this->parser->buildAddress(['AV', 'LOS PROCERES', '-', 'N/A', '123']);

        $this->assertSame('AV LOS PROCERES 123', $address);
    }

    public function testBuildAddressReturnsNullWhenEmpty()
    {
        $address = $this->parser->buildAddress(['-', '', 'NULL']);

        $this->assertNull($address);
    }
}
