<?php

namespace Tests\Unit;

use App\Modules\Agencies\Services\AgencyImportPreviewService;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class AgencyImportPreviewServiceTest extends TestCase
{
    public function test_rejects_non_allowed_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AgencyImportPreviewService)->previewFromUrl('http://example.com/data.json');
    }

    public function test_rejects_private_network_like_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AgencyImportPreviewService)->previewFromUrl('https://127.0.0.1/data.json');
    }

    public function test_preview_transform_payload_does_not_touch_database(): void
    {
        $service = new AgencyImportPreviewService;
        $preview = $service->transformPayload([
            [
                'id' => 3,
                'agencia' => 'Chachapoyas Co Dos De Mayo',
                'departamento' => 'Amazonas ',
                'provincia' => 'Chachapoyas',
                'distrito' => 'Chachapoyas',
                'direccion' => 'jr. dos de mayo',
                'texto_chosen' => 'x',
                'link_mapa' => null,
                'tamano' => 'Grande',
                'co' => true,
            ],
        ]);

        $this->assertCount(1, $preview);
        $this->assertSame('SHA-000003', $preview[0]['code']);
    }

    public function test_preview_rejects_json_root_that_is_not_array(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response(['not' => 'list'], 200),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('array de agencias o un respaldo válido');

        (new AgencyImportPreviewService)->previewFromUrl('https://raw.githubusercontent.com/example/data.json');
    }

    public function test_accepts_official_backup_and_spanish_alias(): void
    {
        $service = new AgencyImportPreviewService;
        $row = ['id' => 3, 'agencia' => 'Tacna'];

        $official = $service->normalizePayload(['metadata' => ['application' => 'CodeRED Platform', 'type' => 'agency-backup', 'module' => 'agencies', 'schema_version' => 1], 'data' => ['agencies' => [$row]]]);
        $spanish = $service->normalizePayload(['agencias' => [$row]]);

        $this->assertSame('data.agencies', $official['format']);
        $this->assertSame('Tacna', $official['agencies'][0]['agencia']);
        $this->assertFalse($official['agencies'][0]['co']);
        $this->assertSame([$row], $spanish['agencies']);
    }

    public function test_rejects_empty_foreign_and_future_backups_in_spanish(): void
    {
        $service = new AgencyImportPreviewService;

        foreach ([
            [[], 'no contiene agencias para importar'],
            [['module' => 'dni', 'agencies' => [['id' => 1]]], 'no corresponde a un respaldo'],
            [['schema_version' => 99, 'agencies' => [['id' => 1]]], 'no es compatible'],
            [['agencies' => ['not' => 'a list']], 'array de agencias o un respaldo válido'],
        ] as [$payload, $message]) {
            try {
                $service->normalizePayload($payload);
                $this->fail('El payload inválido debía rechazarse.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function test_validates_official_metadata_and_record_count(): void
    {
        $service = new AgencyImportPreviewService;
        $row = ['code' => 'SHA-1', 'name' => 'Agencia'];
        foreach ([
            [['metadata' => ['application' => 'Otra', 'type' => 'agency-backup'], 'data' => ['agencies' => [$row]]], 'no fue generado'],
            [['metadata' => ['application' => 'CodeRED Platform', 'type' => 'dni-backup'], 'data' => ['agencies' => [$row]]], 'no corresponde'],
            [['metadata' => ['application' => 'CodeRED Platform', 'type' => 'agency-backup', 'schema_version' => 2], 'data' => ['agencies' => [$row]]], 'Versiones soportadas: 1'],
            [['metadata' => ['application' => 'CodeRED Platform', 'type' => 'agency-backup', 'schema_version' => 1, 'record_count' => 2], 'data' => ['agencies' => [$row]]], 'no coincide'],
            [['metadata' => ['application' => 'CodeRED Platform', 'type' => 'agency-backup', 'schema_version' => 1]], 'no contiene la colecci'],
        ] as [$payload, $message]) {
            try {
                $service->normalizePayload($payload);
                $this->fail('Los metadatos invalidos debian rechazarse.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($message, iconv('UTF-8', 'ASCII//TRANSLIT', $exception->getMessage()));
            }
        }
    }

    public function test_preview_accepts_valid_array_payload(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response([
                [
                    'id' => 3,
                    'agencia' => 'Chachapoyas Co Dos De Mayo',
                    'departamento' => 'Amazonas ',
                    'provincia' => 'Chachapoyas',
                    'distrito' => 'Chachapoyas',
                    'direccion' => 'jr. dos de mayo',
                    'texto_chosen' => 'x',
                    'link_mapa' => null,
                    'tamano' => 'Grande',
                    'co' => true,
                ],
            ], 200),
        ]);

        $payload = (new AgencyImportPreviewService)->previewFromUrl('https://raw.githubusercontent.com/example/data.json');

        $this->assertSame(1, $payload['total_rows']);
        $this->assertSame('SHA-000003', $payload['preview'][0]['code']);
    }
}
