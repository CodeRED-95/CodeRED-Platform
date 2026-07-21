<?php

namespace Tests\Unit;

use App\Modules\Ruc\Support\RucPadronParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RucPadronParserTest extends TestCase
{
    #[Test]
    public function it_parses_legacy_rows_and_preserves_unicode(): void
    {
        $result = app(RucPadronParser::class)->parse("20123456789|COMPAÑÍA ÁRBOL|ACTIVO|HABIDO|150101|||AV. PERÚ 123|LIMA|LIMA|MIRAFLORES\n", '|', 'UTF-8');

        $this->assertSame('20123456789', $result['data']['ruc']);
        $this->assertSame('COMPAÑÍA ÁRBOL', $result['data']['razon_social']);
        $this->assertSame('MIRAFLORES', $result['data']['distrito']);
    }

    #[Test]
    public function it_detects_header_and_rejects_invalid_rows(): void
    {
        $parser = app(RucPadronParser::class);

        $this->assertTrue($parser->parse("RUC|RAZON_SOCIAL\n", '|', 'UTF-8')['header']);
        $this->assertSame('RUC inválido.', $parser->parse("123|EMPRESA\n", '|', 'UTF-8')['error']);
        $this->assertSame('Razón social vacía.', $parser->parse("20123456789|\n", '|', 'UTF-8')['error']);
    }
}
