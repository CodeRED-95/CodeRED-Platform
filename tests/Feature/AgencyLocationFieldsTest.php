<?php

namespace Tests\Feature;

use App\Http\Resources\Api\V1\AgencyResource as PrivateAgencyResource;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportItem;
use App\Modules\Agencies\Models\AgencyImportRun;
use App\Modules\Agencies\Resources\AgencyResource;
use App\Modules\Agencies\Services\AgencyExportService;
use App\Modules\Agencies\Services\AgencyPlaceGenerator;
use App\Services\Agencies\ShalomAgencyNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgencyLocationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_uses_complete_location_and_never_zone(): void
    {
        $agency = new Agency([
            'department' => ' TACNA ',
            'province' => 'TACNA',
            'district' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
            'name' => 'VIÑANIS',
            'category' => 'PEQUEÑA',
        ]);
        $agency->forceFill(['zone' => 'ZONA QUE NO DEBE USARSE']);

        $this->assertSame(
            'TACNA / TACNA / CORONEL GREGORIO ALBARRACIN LANCHIPA / VIÑANIS',
            app(AgencyPlaceGenerator::class)($agency),
        );

        $agency->forceFill(['district' => null]);
        $this->assertSame('TACNA / TACNA / VIÑANIS', app(AgencyPlaceGenerator::class)($agency));
        $this->assertStringNotContainsString('//', app(AgencyPlaceGenerator::class)($agency));
        $this->assertStringNotContainsString('ZONA QUE NO DEBE USARSE', app(AgencyPlaceGenerator::class)($agency));
    }

    public function test_changing_district_and_name_regenerates_place_preserving_accents(): void
    {
        $agency = Agency::factory()->create([
            'department' => 'ÁNCASH',
            'province' => 'HUARAZ',
            'district' => 'INDEPENDENCIA',
            'name' => 'AGENCIA CENTRAL',
        ]);

        $agency->update(['district' => 'SAN MIGUEL DE ACO']);
        $this->assertSame('ÁNCASH / HUARAZ / SAN MIGUEL DE ACO / AGENCIA CENTRAL', $agency->fresh()->place);

        $agency->update(['name' => 'VIÑANIS ÑANDÚ']);
        $this->assertSame('ÁNCASH / HUARAZ / SAN MIGUEL DE ACO / VIÑANIS ÑANDÚ', $agency->fresh()->place);
    }

    public function test_shalom_normalizer_prioritizes_district_and_omits_zone_and_received_place(): void
    {
        $result = app(ShalomAgencyNormalizer::class)->normalize([
            'name' => 'VIÑANIS',
            'category' => 'PEQUEÑA',
            'department' => 'TACNA',
            'province' => 'TACNA',
            'district' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
            'zone' => 'NO USAR',
            'place' => 'PLACE EXTERNO INCOMPLETO',
            'source_record' => [],
        ]);

        $this->assertSame('CORONEL GREGORIO ALBARRACIN LANCHIPA', $result['district']);
        $this->assertArrayNotHasKey('zone', $result);
        $this->assertSame('PLACE EXTERNO INCOMPLETO', $result['place']);
    }

    public function test_resources_export_map_and_search_use_district_without_zone(): void
    {
        $agency = Agency::factory()->create([
            'department' => 'TACNA',
            'province' => 'TACNA',
            'district' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
            'name' => 'VIÑANIS',
            'category' => 'PEQUEÑA',
        ]);
        $request = Request::create('/api/v1/agencies');
        $public = (new AgencyResource($agency))->toArray($request);
        $private = (new PrivateAgencyResource($agency))->toArray($request);
        $export = app(AgencyExportService::class)->forExport($agency);

        $this->assertArrayNotHasKey('zone', $public);
        $this->assertArrayNotHasKey('zone', $private);
        $this->assertSame($agency->district, $public['district']);
        $this->assertSame($agency->district, $private['district']);
        $this->assertSame($agency->district, $export['distrito']);
        $this->assertSame($agency->place, $export['ubicacion_completa']);
        $this->assertTrue(Agency::query()->search('GREGORIO ALBARRACIN')->whereKey($agency->id)->exists());

        $mapSource = file_get_contents(app_path('Livewire/Admin/Agencies/Map.php'));
        $this->assertStringContainsString("'district'", $mapSource);
    }

    public function test_form_and_detail_do_not_expose_zone_and_form_exposes_district(): void
    {
        $form = file_get_contents(resource_path('views/livewire/admin/agencies/form.blade.php'));
        $detail = file_get_contents(resource_path('views/livewire/admin/agencies/show.blade.php'));

        $this->assertStringNotContainsString('wire:model.blur="zone"', $form);
        $this->assertStringNotContainsString('>Zone<', $form.$detail);
        $this->assertStringContainsString("'district'", $form);
        $this->assertStringContainsString('Ubicación completa', $form);
        $this->assertStringContainsString('Generado automáticamente.', $form);
    }

    public function test_repair_command_uses_recent_import_district_and_never_copies_zone(): void
    {
        Storage::fake('local');
        $agency = Agency::factory()->create(['district' => null, 'department' => 'TACNA', 'province' => 'TACNA', 'name' => 'VIÑANIS']);
        $agency->forceFill(['zone' => 'ZONE HISTÓRICO'])->save();
        $run = AgencyImportRun::query()->create(['type' => 'shalom', 'status' => 'completed']);
        AgencyImportItem::query()->create([
            'import_run_id' => $run->id,
            'matched_agency_id' => $agency->id,
            'action' => 'update',
            'incoming_data' => ['district' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA'],
        ]);

        $this->artisan('agencies:repair-location-fields', ['--apply' => true, '--report' => 'reports/test.json'])
            ->expectsConfirmation('¿Aplicar los distritos confirmados por importaciones recientes y regenerar place?', 'yes')
            ->assertSuccessful();

        $agency->refresh();
        $this->assertSame('CORONEL GREGORIO ALBARRACIN LANCHIPA', $agency->district);
        $this->assertSame('ZONE HISTÓRICO', $agency->getAttribute('zone'));
        $this->assertSame('TACNA / TACNA / CORONEL GREGORIO ALBARRACIN LANCHIPA / VIÑANIS', $agency->place);
        Storage::disk('local')->assertExists('reports/test.json');
    }
}
