<?php

namespace Tests\Unit;

use App\Modules\Reniec\Support\ReniecLineParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReniecLineParserTest extends TestCase
{
    #[Test]
    public function it_preserves_zeroes_and_validates_rows(): void
    {
        $p = app(ReniecLineParser::class);
        $data = $p->parse("00000001|MARIA|PEREZ|DIAZ|1990-01-02|F|150101|\n", 1, '|', 'UTF-8');
        $this->assertSame('00000001', $data['data']['dni']);
        $this->assertSame('1990-01-02', $data['data']['fecha_nacimiento']);
        $this->assertSame('invalid_dni', $p->parse('12A45678|A|B|C', 2)['error']);
        $this->assertSame('invalid_column_count', $p->parse('12345678|A', 3)['error']);
    }

    #[Test]
    public function it_tolerates_header_encoding_and_trailing_delimiter(): void
    {
        $p = app(ReniecLineParser::class);
        $this->assertTrue($p->parse("\xEF\xBB\xBFDNI|NOMBRES|PATERNO|MATERNO|", 1, '|', 'UTF-8')['header']);
        $line = mb_convert_encoding('12345678|JOSÉ|PEÑA|MUÑOZ|||150101|', 'ISO-8859-1', 'UTF-8');
        $this->assertSame('JOSÉ', $p->parse($line, 2, '|', 'latin-1')['data']['nombres']);
    }
}
