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
        $result = app(RucPadronParser::class)->parse("20123456789|COMPAÑÍA ÁRBOL|ACTIVO|HABIDO|150101|AV.|PERÚ|123|\n", '|', 'UTF-8');

        $this->assertSame('20123456789', $result['data']['ruc']);
        $this->assertSame('COMPAÑÍA ÁRBOL', $result['data']['razon_social']);
        $this->assertSame('AV. PERÚ 123', $result['data']['direccion']);
    }

    #[Test]
    public function it_detects_header_and_rejects_invalid_rows(): void
    {
        $parser = app(RucPadronParser::class);

        $this->assertTrue($parser->parse("RUC|RAZON_SOCIAL\n", '|', 'UTF-8')['header']);
        $this->assertSame('RUC inválido.', $parser->parse("123|EMPRESA\n", '|', 'UTF-8')['error']);
        $this->assertSame('RUC inválido.', $parser->parse("RUC-INVALIDO|EMPRESA\n", '|', 'UTF-8')['error']);
        $this->assertSame('Razón social vacía.', $parser->parse("20123456789|\n", '|', 'UTF-8')['error']);
    }

    #[Test]
    public function it_parses_the_real_sunat_format_and_trailing_delimiter(): void
    {
        $lines = file(base_path('tests/Fixtures/ruc/sunat-real-format.txt'));
        $this->assertIsArray($lines);
        $parser = app(RucPadronParser::class);
        $this->assertTrue($parser->parse($lines[0], '|', 'UTF-8')['header']);
        $data = $parser->parse($lines[1], '|', 'UTF-8')['data'];
        $this->assertSame('20512805478', $data['ruc']);
        $this->assertSame('150140', $data['ubigeo']);
        $this->assertSame('BL. 51 URB. LA CRUCETA 51 402', $data['direccion']);
    }
}
