<?php

namespace Tests\Unit;

use App\Modules\Agencies\Support\AgencyImportNormalizer;
use Tests\TestCase;

class AgencyImportNormalizerTest extends TestCase
{
    public function test_trims_department_trailing_space(): void
    {
        $row = AgencyImportNormalizer::transform([
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
        ]);

        $this->assertSame('Amazonas', $row->normalized['department']);
    }

    public function test_generates_code_with_zero_padding(): void
    {
        $this->assertSame('SHA-000003', AgencyImportNormalizer::generateCode(3));
    }

    public function test_extracts_negative_coordinates(): void
    {
        [$lat, $lng] = AgencyImportNormalizer::parseCoordinates('https://www.google.com/maps/dir/?api=1&destination=-6.238673290149498,-77.86800826533634');

        $this->assertSame(-6.238673290149498, $lat);
        $this->assertSame(-77.86800826533634, $lng);
    }

    public function test_link_without_coordinates_returns_nulls(): void
    {
        [$lat, $lng] = AgencyImportNormalizer::parseCoordinates('https://example.com/maps');

        $this->assertNull($lat);
        $this->assertNull($lng);
    }

    public function test_normalizes_large_size(): void
    {
        $this->assertSame('large', AgencyImportNormalizer::normalizeSize('Grande'));
    }

    public function test_unknown_size_becomes_null(): void
    {
        $this->assertNull(AgencyImportNormalizer::normalizeSize('Gigante'));
    }

    public function test_boolean_co_conversion(): void
    {
        $warnings = [];

        $this->assertTrue(AgencyImportNormalizer::parseOperationsCenter('true', $warnings));
        $this->assertFalse(AgencyImportNormalizer::parseOperationsCenter('false', $warnings));
        $this->assertTrue(AgencyImportNormalizer::parseOperationsCenter(1, $warnings));
        $this->assertFalse(AgencyImportNormalizer::parseOperationsCenter(0, $warnings));
    }

    public function test_invalid_boolean_warns_and_defaults_false(): void
    {
        $warnings = [];

        $value = AgencyImportNormalizer::parseOperationsCenter('maybe', $warnings);

        $this->assertFalse($value);
        $this->assertNotEmpty($warnings);
    }

    public function test_slug_duplicate_can_be_suffixed(): void
    {
        $this->assertSame('chachapoyas-co-dos-de-mayo-3', AgencyImportNormalizer::slugifyUnique('Chachapoyas Co Dos De Mayo', '3'));
    }

    public function test_transforms_example_payload(): void
    {
        $row = AgencyImportNormalizer::transform([
            'id' => 3,
            'agencia' => 'Chachapoyas Co Dos De Mayo',
            'departamento' => 'Amazonas ',
            'provincia' => 'Chachapoyas',
            'distrito' => 'Chachapoyas',
            'direccion' => 'jr. dos de mayo cdra. 15 s/n chachapoyas, referencia: junto a terminal de combis etsa',
            'texto_chosen' => '3 - AMAZONAS - CHACHAPOYAS - CHACHAPOYAS - CHACHAPOYAS CO DOS DE MAYO - TERRESTRE',
            'link_mapa' => 'https://www.google.com/maps/dir/?api=1&destination=-6.238673290149498,-77.86800826533634',
            'tamano' => 'Grande',
            'co' => true,
        ]);

        $this->assertSame('SHA-000003', $row->normalized['code']);
        $this->assertSame('Chachapoyas Co Dos De Mayo', $row->normalized['name']);
        $this->assertSame('Amazonas', $row->normalized['department']);
        $this->assertSame(-6.238673290149498, $row->normalized['latitude']);
        $this->assertSame('large', $row->normalized['size']);
        $this->assertTrue($row->normalized['is_operations_center']);
        $this->assertSame('github_gist', $row->normalized['source']);
        $this->assertSame('3', $row->normalized['source_reference']);
        $this->assertSame('under_review', $row->normalized['status']);
    }

    public function test_missing_id_is_invalid(): void
    {
        $row = AgencyImportNormalizer::transform([
            'agencia' => 'X',
        ]);

        $this->assertFalse($row->valid);
        $this->assertNotEmpty($row->errors);
    }

    public function test_missing_agency_is_invalid(): void
    {
        $row = AgencyImportNormalizer::transform([
            'id' => 1,
        ]);

        $this->assertFalse($row->valid);
        $this->assertNotEmpty($row->errors);
    }
}
