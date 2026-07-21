<?php

namespace Tests\Feature;

use App\Modules\Agencies\Actions\ImportAgenciesAction;
use App\Modules\Agencies\Enums\AgencyImportStatus;
use App\Modules\Agencies\Enums\AgencyImportStrategy;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ValueError;

class AgencyImportActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_agency_with_requested_default_status(): void
    {
        $import = $this->import(AgencyImportStrategy::IgnoreExisting);

        $summary = app(ImportAgenciesAction::class)->execute(
            $import,
            [$this->row(7, 'Dirección inicial')],
            AgencyStatus::Active->value
        );

        $this->assertSame(1, $summary['imported']);
        $this->assertSame(0, $summary['failed']);
        $this->assertDatabaseHas('agency_sync_changes', ['code' => 'SHA-000007', 'operation' => 'upsert']);
        $this->assertDatabaseHas('agencies', [
            'code' => 'SHA-000007',
            'source' => 'github_gist',
            'source_reference' => '7',
            'status' => AgencyStatus::Active->value,
        ]);
    }

    public function test_import_updates_existing_agency_with_update_strategy(): void
    {
        $existing = Agency::factory()->create([
            'code' => 'SHA-000008',
            'source' => 'github_gist',
            'source_reference' => '8',
            'address' => 'Dirección anterior',
        ]);
        $import = $this->import(AgencyImportStrategy::UpdateExisting);

        $summary = app(ImportAgenciesAction::class)->execute(
            $import,
            [$this->row(8, 'Dirección actualizada')]
        );

        $this->assertSame(1, $summary['updated']);
        $this->assertSame('Dirección actualizada', $existing->fresh()->address);
        $this->assertDatabaseHas('agency_sync_changes', ['agency_internal_id' => $existing->id, 'operation' => 'upsert']);
    }

    public function test_import_persists_new_chosen_format_and_external_id(): void
    {
        $row = $this->row(610, 'Dirección');
        unset($row['texto_chosen']);
        $row['texto_chosen_terrestre'] = '610 - TERRESTRE';
        $row['texto_chosen_aereo'] = '610 - AEREO';

        $summary = app(ImportAgenciesAction::class)->execute($this->import(AgencyImportStrategy::IgnoreExisting), [$row]);

        $this->assertSame(1, $summary['imported']);
        $this->assertDatabaseHas('agencies', [
            'external_id' => 610,
            'code' => 'SHA-000610',
            'texto_chosen_terrestre' => '610 - TERRESTRE',
            'texto_chosen_aereo' => '610 - AEREO',
        ]);
    }

    public function test_import_rejects_duplicate_external_id_inside_file(): void
    {
        $summary = app(ImportAgenciesAction::class)->execute($this->import(AgencyImportStrategy::UpdateExisting), [
            $this->row(620, 'Primera'),
            $this->row(620, 'Segunda'),
        ]);

        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['failed']);
    }

    public function test_import_rejects_external_id_and_code_identity_conflict(): void
    {
        Agency::factory()->create(['external_id' => 630, 'code' => 'OTHER-630', 'source_reference' => '630']);
        Agency::factory()->create(['external_id' => 631, 'code' => 'SHA-000630', 'source_reference' => '631']);

        $summary = app(ImportAgenciesAction::class)->execute($this->import(AgencyImportStrategy::UpdateExisting), [$this->row(630, 'Conflicto')]);

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(1, $summary['identity_conflicts']);
        $this->assertDatabaseMissing('agencies', ['address' => 'Conflicto']);
    }

    public function test_official_restore_ignores_old_ids_handles_types_soft_deletes_and_moves(): void
    {
        $existing = Agency::factory()->create(['code' => 'RESTORE-001', 'external_id' => null]);
        $existing->delete();
        $base = [
            '_backup_record' => true, 'external_id' => null, 'departamento' => 'Lima',
            'provincia' => 'Lima', 'distrito' => 'Miraflores', 'direccion' => 'Direccion',
            'source' => 'manual', 'co' => '0', 'latitude' => '-12.1234567', 'longitude' => -77.1234567,
        ];
        $rows = [
            [...$base, '_backup_id' => 999, '_backup_moved_to_id' => null, '_backup_deleted_at' => null, 'code' => 'RESTORE-001', 'agencia' => 'Restaurada', 'services' => '[]'],
            [...$base, '_backup_id' => 1000, '_backup_moved_to_id' => 1001, '_backup_deleted_at' => null, 'code' => 'MOVE-001', 'agencia' => 'Trasladada', 'has_moved' => '1', 'services' => '["air"]'],
            [...$base, '_backup_id' => 1001, '_backup_moved_to_id' => null, '_backup_deleted_at' => '2026-07-20 12:00:00', 'code' => 'TARGET-001', 'agencia' => 'Destino', 'services' => []],
        ];

        $summary = app(ImportAgenciesAction::class)->execute($this->import(AgencyImportStrategy::UpdateExisting), $rows);

        $moved = Agency::where('code', 'MOVE-001')->firstOrFail();
        $target = Agency::withTrashed()->where('code', 'TARGET-001')->firstOrFail();
        $this->assertSame(1, $summary['restored']);
        $this->assertFalse($existing->fresh()->trashed());
        $this->assertTrue($target->trashed());
        $this->assertSame($target->id, $moved->moved_to_agency_id);
        $this->assertSame(['air'], $moved->services);
        $this->assertNotSame(1000, $moved->id);
        $this->assertFalse($existing->fresh()->is_operations_center);
    }

    public function test_imports_exactly_488_agencies_and_rolls_back_on_unexpected_error(): void
    {
        $rows = [];
        for ($id = 1; $id <= 488; $id++) {
            $rows[] = $this->row(10000 + $id, 'Direccion '.$id);
        }
        $summary = app(ImportAgenciesAction::class)->execute($this->import(AgencyImportStrategy::IgnoreExisting), $rows);
        $this->assertSame(488, $summary['imported']);
        $this->assertDatabaseCount('agencies', 488);

        $invalidImport = $this->import(AgencyImportStrategy::IgnoreExisting);
        $invalidImport->strategy = 'invalid-strategy';
        $invalidImport->save();
        try {
            app(ImportAgenciesAction::class)->execute($invalidImport, [
                $this->row(20001, 'Se debe revertir'),
                $this->row(10001, 'Dispara estrategia invalida'),
            ]);
            $this->fail('La estrategia invalida debia fallar.');
        } catch (ValueError) {
            $this->assertDatabaseMissing('agencies', ['external_id' => 20001]);
        }
    }

    private function import(AgencyImportStrategy $strategy): AgencyImport
    {
        return AgencyImport::query()->create([
            'original_filename' => 'agencies.json',
            'stored_filename' => 'tests/agencies.json',
            'file_type' => 'json',
            'status' => AgencyImportStatus::Processing,
            'strategy' => $strategy->value,
            'total_rows' => 1,
        ]);
    }

    private function row(int $id, string $address): array
    {
        return [
            'id' => $id,
            'agencia' => 'Agencia de prueba '.$id,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Miraflores',
            'direccion' => $address,
            'texto_chosen' => 'Texto de prueba',
            'link_mapa' => null,
            'tamano' => 'Mediano',
            'co' => false,
        ];
    }
}
