<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ClipboardPayloadFormatter;
use PHPUnit\Framework\TestCase;

class ClipboardPayloadFormatterTest extends TestCase
{
    public function test_formats_json_and_readable_text_recursively(): void
    {
        $payload = [
            'success' => true,
            'data' => [
                'ruc' => '20123456789',
                'razon_social' => 'EMPRESA SAC',
                'estado' => 'ACTIVO',
                'condicion' => null,
                'ubigeo' => '150101',
                'direccion' => [
                    'linea_1' => 'AV. PRINCIPAL 123',
                    'referencia' => null,
                ],
                'telefonos' => ['987654321', null, '999888777'],
            ],
        ];

        $json = ClipboardPayloadFormatter::json($payload);
        $this->assertStringContainsString('"success": true', $json);
        $this->assertStringContainsString('"razon_social": "EMPRESA SAC"', $json);

        $readable = ClipboardPayloadFormatter::readable($payload['data']);
        $this->assertStringContainsString('RUC: 20123456789', $readable);
        $this->assertStringContainsString('Razón Social: EMPRESA SAC', $readable);
        $this->assertStringContainsString('Estado: ACTIVO', $readable);
        $this->assertStringContainsString('Dirección:', $readable);
        $this->assertStringContainsString('Linea 1: AV. PRINCIPAL 123', $readable);
        $this->assertStringContainsString('987654321', $readable);
        $this->assertStringNotContainsString('null', $readable);
    }
}
