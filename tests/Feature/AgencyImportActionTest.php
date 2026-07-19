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
