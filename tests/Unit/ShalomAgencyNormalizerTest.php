<?php

namespace Tests\Unit;

use App\Services\Agencies\ShalomAgencyNormalizer;
use Tests\TestCase;

class ShalomAgencyNormalizerTest extends TestCase
{
    public function test_normalizes_example_payload_and_recovers_district_from_zone(): void
    {
        $normalizer = new ShalomAgencyNormalizer;
        $row = $normalizer->normalize([
            'external_id' => 674,
            'code' => 'VNNS',
            'name' => 'VIÑANIS',
            'place' => 'TACNA / TACNA / CORONEL GREGORIO ALBARRACIN LANCHIPA / VIÑANIS',
            'zone' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
            'department' => 'TACNA',
            'province' => 'TACNA',
            'address' => 'PROMUVI VIÑANI, AMP. I ETAPA, MZ. 574, LT. 09 – CORONEL GREGORIO ALBARRACÍN LANCHIPA – TACNA - TACNA, REF. A MEDIA CDRA. DEL ÓVALO LOS MOLLES',
            'latitude' => '-18.062945787541',
            'longitude' => '-70.251860014921',
            'schedule' => [
                'general' => 'LUNES A VIERNES - 8:00 AM A 8:00 PM',
                'sunday' => 'DOMINGOS DE 8:00 AM A 5:00 PM',
            ],
            'classification' => [
                'category' => 'PEQUEÑA',
                'sends_category' => 'HASTA 75 KG / 1 M3',
                'receives_category' => 'HASTA 75 KG / 0.5 M3',
            ],
            'geographic_ids' => ['ubigeo_id' => '230110'],
            'services' => ['air' => true],
            'source_record' => [
                'ter_id' => 674,
                'ter_abrebiatura' => 'VNNS',
                'lugar_over' => 'VIÑANIS',
                'nombre' => 'TACNA / TACNA / CORONEL GREGORIO ALBARRACIN LANCHIPA / VIÑANIS',
                'zona' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
                'departamento' => 'TACNA',
                'provincia' => 'TACNA',
                'direccion' => 'PROMUVI VIÑANI, AMP. I ETAPA, MZ. 574, LT. 09 – CORONEL GREGORIO ALBARRACÍN LANCHIPA – TACNA - TACNA, REF. A MEDIA CDRA. DEL ÓVALO LOS MOLLES',
                'latitud' => '-18.062945787541',
                'longitud' => '-70.251860014921',
                'hora_atencion' => 'LUNES A VIERNES - 8:00 AM A 8:00 PM',
                'hora_domingo' => 'DOMINGOS DE 8:00 AM A 5:00 PM',
                'ter_categoria' => 'PEQUEÑA',
                'ter_categoria_envia' => 'HASTA 75 KG / 1 M3',
                'ter_categoria_recibe' => 'HASTA 75 KG / 0.5 M3',
                'ubi_id' => 230110,
                'ter_aereo' => true,
            ],
        ]);

        $this->assertSame('CORONEL GREGORIO ALBARRACIN LANCHIPA', $row['district']);
        $this->assertSame('PEQUEÑA', $row['classification_category']);
        $this->assertSame(230110, $row['ubigeo_id']);
        $this->assertSame('674 - TACNA - TACNA - CORONEL GREGORIO ALBARRACIN LANCHIPA - VIÑANIS - TERRESTRE', $row['texto_chosen_terrestre']);
        $this->assertSame('674 - TACNA - TACNA - CORONEL GREGORIO ALBARRACIN LANCHIPA - VIÑANIS - AEREO', $row['texto_chosen_aereo']);
        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination=-18.062945787541,-70.251860014921', $row['map_url']);
    }

    public function test_empty_coordinates_become_null(): void
    {
        $row = (new ShalomAgencyNormalizer)->normalize([
            'external_id' => 'null',
            'latitude' => ' ',
            'longitude' => '',
            'source_record' => [
                'latitud' => 'null',
                'longitud' => null,
            ],
        ]);

        $this->assertNull($row['external_id']);
        $this->assertNull($row['latitude']);
        $this->assertNull($row['longitude']);
    }
}
