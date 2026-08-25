<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Agencies\Support\AgencySyncFields;
use PHPUnit\Framework\TestCase;

/**
 * La vista previa proponia actualizar las mismas agencias en cada ejecucion,
 * por muchas veces que se confirmara la importacion. Estas pruebas fijan las
 * tres causas para que no vuelvan.
 */
class AgencySyncIdempotencyTest extends TestCase
{
    public function test_una_coordenada_leida_como_texto_equivale_al_float_entrante(): void
    {
        // Causa principal: el modelo devuelve la coordenada como cadena (ver
        // NullableCoordinate) y el extractor la entrega como float, de modo
        // que `!==` daba distinto en TODAS las agencias con coordenadas.
        $this->assertTrue(AgencySyncFields::matches('latitude', '-8.190082547884', -8.190082547884));
        $this->assertTrue(AgencySyncFields::matches('longitude', '-76.535472388443', -76.535472388443));
    }

    public function test_los_decimales_que_la_columna_no_guarda_no_cuentan_como_cambio(): void
    {
        // La columna es numeric(15,12): el decimal decimotercero se pierde al
        // guardar, asi que compararlo sin redondear era otra diferencia eterna.
        $this->assertTrue(AgencySyncFields::matches('latitude', '-8.190082547884', -8.1900825478844));
        $this->assertSame(-8.190082547884, AgencySyncFields::roundCoordinate(-8.1900825478844));
    }

    public function test_una_coordenada_distinta_de_verdad_si_cuenta_como_cambio(): void
    {
        $this->assertFalse(AgencySyncFields::matches('latitude', '-8.190082547884', -8.1900825));
        $this->assertFalse(AgencySyncFields::matches('latitude', null, -8.19));
    }

    public function test_un_entero_y_su_cadena_no_son_un_cambio(): void
    {
        $this->assertTrue(AgencySyncFields::matches('external_id', 566, '566'));
        $this->assertFalse(AgencySyncFields::matches('external_id', 566, '567'));
    }

    public function test_los_campos_que_no_se_escriben_quedan_fuera_de_la_comparacion(): void
    {
        // source_record ni siquiera es columna de agencies; map_url, place,
        // ubigeo_id e is_operations_center existen pero la confirmacion no los
        // aplica. Compararlos era proponer un cambio imposible de consolidar.
        foreach (['source_record', 'map_url', 'place', 'ubigeo_id', 'ubigeo_code', 'is_operations_center', 'source'] as $field) {
            $this->assertNotContains($field, AgencySyncFields::FIELDS, "«{$field}» no debe entrar en la comparación: la importación no lo escribe.");
        }
    }

    public function test_los_campos_comparados_son_exactamente_los_que_se_escriben(): void
    {
        // Si alguien añade un campo a la escritura y olvida la comparacion
        // (o al reves), esta prueba lo detecta: la lista es una sola.
        $this->assertContains('name', AgencySyncFields::FIELDS);
        $this->assertContains('latitude', AgencySyncFields::FIELDS);
        $this->assertContains('texto_chosen_terrestre', AgencySyncFields::FIELDS);
        $this->assertCount(16, AgencySyncFields::FIELDS);
    }
}
