<?php

namespace Tests\Feature;

use App\Http\Resources\Api\AgencyResource;
use App\Models\User;
use App\Modules\Agencies\Actions\ConfirmAgencyImportRunAction;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportItem;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShalomAgencyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_existing_agency_by_code_when_external_id_is_missing(): void
    {
        $user = User::factory()->create();
        $agency = Agency::factory()->create([
            'code' => 'VNNS',
            'external_id' => null,
            'status' => AgencyStatus::UnderReview,
        ]);

        $run = AgencyImportRun::create(['type' => 'shalom', 'status' => 'ready_for_review', 'chosen_storage_path' => 'imports/chosen.txt']);
        AgencyImportItem::create([
            'import_run_id' => $run->id,
            'external_id' => null,
            'matched_agency_id' => null,
            'action' => 'create',
            'confidence' => 0,
            'incoming_data' => [
                'external_id' => null,
                'code' => 'VNNS',
                'name' => 'VIÑANIS',
                'department' => 'TACNA',
                'province' => 'TACNA',
                'district' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
                'address' => 'NUEVA DIRECCION',
                'latitude' => -18.1,
                'longitude' => -70.2,
                'schedule_general' => null,
                'schedule_sunday' => null,
                'classification_category' => null,
                'classification_sends_category' => null,
                'classification_receives_category' => null,
                'texto_chosen_terrestre' => null,
                'texto_chosen_aereo' => null,
            ],
            'current_data' => [],
            'differences' => [],
            'proposed_old_name' => null,
            'conflict_reason' => null,
            'selected' => true,
        ]);

        (new ConfirmAgencyImportRunAction)->execute($run, $user->id);

        $this->assertDatabaseHas('agencies', [
            'id' => $agency->id,
            'code' => 'VNNS',
            'name' => 'VIÑANIS',
            'address' => 'NUEVA DIRECCION',
        ]);
    }

    public function test_public_resource_uses_expected_shape(): void
    {
        $agency = Agency::factory()->create([
            'status' => AgencyStatus::UnderReview,
            'external_id' => 674,
            'code' => 'VNNS',
            'name' => 'VIÑANIS',
            'department' => 'TACNA',
            'province' => 'TACNA',
            'district' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
            'latitude' => -18.062945787541,
            'longitude' => -70.251860014921,
            'schedule_general' => 'LUNES A VIERNES - 8:00 AM A 8:00 PM',
            'schedule_sunday' => 'DOMINGOS DE 8:00 AM A 5:00 PM',
            'classification_category' => 'PEQUEÑA',
            'classification_sends_category' => 'HASTA 75 KG / 1 M3',
            'classification_receives_category' => 'HASTA 75 KG / 0.5 M3',
            'texto_chosen_terrestre' => '674 - TACNA - TACNA - CORONEL GREGORIO ALBARRACIN LANCHIPA - VIÑANIS - TERRESTRE',
            'texto_chosen_aereo' => '674 - TACNA - TACNA - CORONEL GREGORIO ALBARRACIN LANCHIPA - VIÑANIS - AEREO',
            'is_operations_center' => false,
        ]);

        $payload = (new AgencyResource($agency))->toArray(request());

        $this->assertSame('LUNES A VIERNES - 8:00 AM A 8:00 PM', $payload['schedule']['general']);
        $this->assertSame('PEQUEÑA', $payload['classification']['tamano']);
        $this->assertIsFloat($payload['latitude']);
        $this->assertIsFloat($payload['longitude']);
        $this->assertFalse($payload['centro_operaciones']);
        $this->assertSame('under_review', $payload['status']);
        $this->assertSame('En revisión', $payload['estado']);
    }
}
