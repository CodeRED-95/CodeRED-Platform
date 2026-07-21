<?php

namespace Tests\Unit;

use App\Modules\Ruc\Services\AlanubeUbigeoParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlanubeUbigeoParserTest extends TestCase
{
    #[Test]
    public function it_maps_alanube_city_column_to_province_and_capital(): void
    {
        $rows = app(AlanubeUbigeoParser::class)->parse($this->html([
            ['010101', 'AMAZONAS', 'CHACHAPOYAS', 'CHACHAPOYAS', 'CHACHAPOYAS'],
            ['150137', 'LIMA', 'LIMA', 'SANTA ANITA', 'SANTA ANITA'],
            ['150140', 'LIMA', 'LIMA', 'SANTIAGO DE SURCO', 'SANTIAGO DE SURCO'],
        ]));

        $this->assertSame('CHACHAPOYAS', $rows[0]['provincia']);
        $this->assertSame('SANTA ANITA', $rows[1]['distrito']);
        $this->assertSame('SANTIAGO DE SURCO', $rows[2]['distrito']);
        $this->assertSame('SANTIAGO DE SURCO', $rows[2]['capital']);
    }

    #[Test]
    public function it_rejects_incomplete_structured_rows(): void
    {
        $this->expectExceptionMessage('está incompleta');
        app(AlanubeUbigeoParser::class)->parse('<table><tr><td>010101</td><td>-</td><td>AMAZONAS</td></tr></table>');
    }

    private function html(array $rows): string
    {
        return '<table>'.implode('', array_map(fn (array $row): string => '<tr><td><code>'.$row[0].'</code></td><td>-</td><td>'.implode('</td><td>', array_slice($row, 1)).'</td></tr>', $rows)).'</table>';
    }
}
