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
