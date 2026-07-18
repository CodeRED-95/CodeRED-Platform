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
        (new AgencyImportPreviewService())->previewFromUrl('http://example.com/data.json');
    }

    public function test_rejects_private_network_like_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AgencyImportPreviewService())->previewFromUrl('https://127.0.0.1/data.json');
    }

    public function test_preview_transform_payload_does_not_touch_database(): void
    {
        $service = new AgencyImportPreviewService();
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
        $this->expectExceptionMessage('El JSON raíz debe ser un array.');

        (new AgencyImportPreviewService())->previewFromUrl('https://raw.githubusercontent.com/example/data.json');
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

        $payload = (new AgencyImportPreviewService())->previewFromUrl('https://raw.githubusercontent.com/example/data.json');

        $this->assertSame(1, $payload['total_rows']);
        $this->assertSame('SHA-000003', $payload['preview'][0]['code']);
    }
}
