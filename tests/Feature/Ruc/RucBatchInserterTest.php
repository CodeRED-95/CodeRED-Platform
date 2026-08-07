<?php

namespace Tests\Feature\Ruc;

use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Models\RucRecord;
use App\Modules\Ruc\Services\RucBatchInserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RucBatchInserterTest extends TestCase
{
    use RefreshDatabase;

    private function record(string $ruc, string $razonSocial): array
    {
        return [
            'ruc' => $ruc,
            'razon_social' => $razonSocial,
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
            'ubigeo' => '150101',
            'direccion' => 'AV. TEST 123',
        ];
    }

    /**
     * Regresión: el UPSERT generaba 11 placeholders mandando solo 8 valores
     * por fila, lo que hacía fallar el statement en cada lote y degradaba
     * silenciosamente a insertOrIgnore() (perdiendo la estrategia de merge).
     */
    public function test_batch_insert_applies_bulk_upsert_without_falling_back(): void
    {
        $import = RucImport::factory()->create(['merge_strategy' => 'insert']);

        $inserter = app(RucBatchInserter::class);
        $result = $inserter->insert($import, [
            $this->record('20111111111', 'EMPRESA UNO'),
            $this->record('20222222222', 'EMPRESA DOS'),
        ], 'insert');

        $this->assertSame(2, $result->inserted);
        $this->assertSame(0, $result->skipped);
        $this->assertDatabaseHas('ruc_records', ['ruc' => '20111111111', 'ruc_import_id' => $import->id]);
        $this->assertDatabaseHas('ruc_records', ['ruc' => '20222222222', 'ruc_import_id' => $import->id]);
    }

    /**
     * Con el bug de placeholders, el fallback SIEMPRE usaba insertOrIgnore(),
     * así que la estrategia insert_update nunca actualizaba nada aunque el
     * UI reportara éxito. Este test falla con el bug presente.
     */
    public function test_batch_insert_update_strategy_actually_updates_existing_record(): void
    {
        $originalImport = RucImport::factory()->create();
        RucRecord::create([...$this->record('20123456789', 'NOMBRE VIEJO'), 'ruc_import_id' => $originalImport->id]);

        $updatingImport = RucImport::factory()->create(['merge_strategy' => 'insert_update']);
        $inserter = app(RucBatchInserter::class);
        $inserter->insert($updatingImport, [
            $this->record('20123456789', 'NOMBRE NUEVO'),
        ], 'insert_update');

        $this->assertDatabaseHas('ruc_records', [
            'ruc' => '20123456789',
            'razon_social' => 'NOMBRE NUEVO',
            // ruc_import_id no se sobrescribe: debe seguir apuntando a quien
            // creó el registro originalmente (necesario para que el rollback
            // de una importación no borre registros que solo actualizó).
            'ruc_import_id' => $originalImport->id,
        ]);
    }
}
