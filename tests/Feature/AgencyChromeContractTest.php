<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Ruc\Models\Ubigeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyChromeContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_contract_exposes_safe_public_fields_required_by_chrome_extension(): void
    {
        $ubigeo = Ubigeo::query()->create([
            'codigo' => '230110',
            'departamento' => 'TACNA',
            'provincia' => 'TACNA',
            'distrito' => 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
        ]);

        $destination = Agency::factory()->create([
            'status' => AgencyStatus::Active,
            'code' => 'DST001',
            'name' => 'DESTINO TACNA',
        ]);

        $agency = Agency::factory()->create([
            'status' => AgencyStatus::Moved,
            'has_moved' => true,
            'moved_to_agency_id' => $destination->id,
            'moved_to_address' => 'Av. Nueva 123',
            'short_name' => 'Vinanis',
            'slug' => 'vinanis',
            'ubigeo_id' => $ubigeo->id,
            'reference' => 'Frente al mercado',
            'phone' => '999111222',
            'secondary_phone' => '999333444',
            'email' => 'agencia@example.test',
            'schedule' => 'Lunes a sabado',
            'schedule_general' => 'Lunes a sabado 8:00 a 18:00',
            'schedule_sunday' => 'Domingo cerrado',
            'classification_category' => 'GRANDE / CO',
            'classification_sends_category' => 'Envia paquetes',
            'classification_receives_category' => 'Recibe paquetes',
            'observations' => 'Atencion temporal en nueva sede.',
        ]);

        $response = $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/agencies/'.$agency->code);

        $response->assertOk()
            ->assertJsonPath('data.short_name', 'Vinanis')
            ->assertJsonPath('data.slug', 'vinanis')
            ->assertJsonPath('data.ubigeo_id', $ubigeo->id)
            ->assertJsonPath('data.reference', 'Frente al mercado')
            ->assertJsonPath('data.phone', '999111222')
            ->assertJsonPath('data.secondary_phone', '999333444')
            ->assertJsonPath('data.email', 'agencia@example.test')
            ->assertJsonPath('data.schedule.general', 'Lunes a sabado 8:00 a 18:00')
            ->assertJsonPath('data.schedule_sunday', 'Domingo cerrado')
            ->assertJsonPath('data.classification.category', 'GRANDE / CO')
            ->assertJsonPath('data.classification.sends_category', 'Envia paquetes')
            ->assertJsonPath('data.classification.receives_category', 'Recibe paquetes')
            ->assertJsonPath('data.is_operations_center', true)
            ->assertJsonPath('data.has_moved', true)
            ->assertJsonPath('data.moved_to_agency_id', $destination->external_id)
            ->assertJsonPath('data.moved_to_agency_code', 'DST001')
            ->assertJsonPath('data.moved_to_agency_name', 'DESTINO TACNA')
            ->assertJsonPath('data.moved_to_address', 'Av. Nueva 123')
            ->assertJsonPath('data.observations', 'Atencion temporal en nueva sede.')
            ->assertJsonPath('data.updated_at', $agency->updated_at?->toIso8601String());
    }

    /** @return array<string, string> */
    private function tokenHeaders(array $abilities = ['agencies:read']): array
    {
        $token = User::factory()->create()->createToken('Prueba Chrome', $abilities)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }
}
