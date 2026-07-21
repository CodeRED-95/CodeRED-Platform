<?php

namespace Tests\Unit;

use App\Modules\Ruc\Support\EncodingNormalizer;
use App\Modules\Ruc\Support\RucPadronParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EncodingNormalizerTest extends TestCase
{
    public static function aliases(): array
    {
        return [
            ['latin-1', 'ISO-8859-1'], ['latin1', 'ISO-8859-1'], ['latin_1', 'ISO-8859-1'],
            ['ISO-8859-1', 'ISO-8859-1'], ['iso8859-1', 'ISO-8859-1'],
            ['cp1252', 'Windows-1252'], ['windows-1252', 'Windows-1252'], ['win-1252', 'Windows-1252'],
            ['utf8', 'UTF-8'], ['UTF-8', 'UTF-8'],
        ];
    }

    #[Test]
    #[DataProvider('aliases')]
    public function it_normalizes_common_aliases(string $input, string $expected): void
    {
        $this->assertSame($expected, EncodingNormalizer::normalize($input));
    }

    #[Test]
    public function it_rejects_unknown_encoding_with_clear_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no es compatible');
        EncodingNormalizer::normalize('made-up-encoding');
    }

    #[Test]
    public function it_parses_real_iso_and_windows_bytes(): void
    {
        $parser = app(RucPadronParser::class);
        $iso = mb_convert_encoding("20123456789|PEÑA MUÑOZ ÁÉÍÓÚ S.A.C.\n", 'ISO-8859-1', 'UTF-8');
        $windows = mb_convert_encoding("20123456780|PEÑA € S.A.C.\n", 'Windows-1252', 'UTF-8');

        $this->assertSame('PEÑA MUÑOZ ÁÉÍÓÚ S.A.C.', $parser->parse($iso, '|', 'latin-1')['data']['razon_social']);
        $this->assertSame('PEÑA € S.A.C.', $parser->parse($windows, '|', 'cp1252')['data']['razon_social']);
    }
}
