<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre específicamente el grupo en español GET /api/v1/agencias (ability
 * agencias:consultar) — la ruta que realmente usa el proxy de Declaración
 * Jurada Shalom (server/app-backend.js: handleAgencias) para poblar el
 * modal "Buscar Sede Shalom". Comparte controlador con /api/v1/agencies
 * (ya cubierto en ApiV1SecurityTest), pero esa ruta usa otra ability
 * (agencies:read) y no tenía cobertura propia de auth/búsqueda/orden para
 * el grupo español.
 */
class AgenciasApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_sin_token_responde_no_autenticado(): void
    {
        $this->getJson('/api/v1/agencias')->assertUnauthorized();
    }

    public function test_token_sin_ability_agencias_consultar_responde_prohibido(): void
    {
        $this->withToken($this->token(['dni:consultar']))
            ->getJson('/api/v1/agencias')
            ->assertForbidden();
    }

    public function test_token_con_ability_agencias_consultar_puede_listar(): void
    {
        Agency::factory()->create(['name' => 'Agencia Prueba', 'status' => AgencyStatus::Active]);

        $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_busqueda_por_nombre(): void
    {
        // department se fija explícitamente en AMBAS agencias: el factory
        // elige el departamento al azar entre un puñado de valores fijos
        // que incluye literalmente "Piura" (ver AgencyFactory::definition),
        // así que sin fijarlo la agencia "de control" podía coincidir por
        // azar con el término buscado y romper el conteo esperado.
        Agency::factory()->create(['name' => 'Agencia Piura Centro', 'code' => 'PIU-001', 'department' => 'Lima', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Agencia Tacna', 'code' => 'TAC-001', 'department' => 'Lima', 'status' => AgencyStatus::Active]);

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?agencia=Piura');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.agencia', 'Agencia Piura Centro');
    }

    public function test_busqueda_por_departamento(): void
    {
        Agency::factory()->create(['name' => 'Sede Amazonas Uno', 'department' => 'Amazonas', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Sede Lima Uno', 'department' => 'Lima', 'status' => AgencyStatus::Active]);

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?departamento=Amazonas');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.agencia', 'Sede Amazonas Uno');
    }

    public function test_busqueda_por_provincia(): void
    {
        Agency::factory()->create(['name' => 'Sede Bongara', 'province' => 'Bongara', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Sede Otra Provincia', 'province' => 'Chachapoyas', 'status' => AgencyStatus::Active]);

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?provincia=Bongara');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.agencia', 'Sede Bongara');
    }

    public function test_busqueda_por_distrito(): void
    {
        Agency::factory()->create(['name' => 'Sede Jazan', 'district' => 'Jazan', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Sede Otro Distrito', 'district' => 'Yambrasbamba', 'status' => AgencyStatus::Active]);

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?distrito=Jazan');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.agencia', 'Sede Jazan');
    }

    public function test_busqueda_por_direccion(): void
    {
        Agency::factory()->create(['name' => 'Sede Con Direccion Unica', 'address' => 'AV. PEDRO RUIZ GALLO 123', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Sede Otra Direccion', 'address' => 'CALLE LOS OLIVOS 456', 'status' => AgencyStatus::Active]);

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?agencia=PEDRO+RUIZ');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.agencia', 'Sede Con Direccion Unica');
    }

    public function test_busqueda_sin_resultados_devuelve_listado_vacio(): void
    {
        Agency::factory()->create(['name' => 'Agencia Existente', 'status' => AgencyStatus::Active]);

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?agencia=NoExisteNiParecido');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_paginacion(): void
    {
        // Se filtra por un marcador único de esta prueba (agencia=) para
        // que el conteo por página no dependa de cuántas agencias existan
        // en total en la base de pruebas — otros archivos de esta suite
        // usan DatabaseTruncation (commits reales, no rollback) y pueden
        // dejar agencias adicionales visibles aquí.
        for ($i = 1; $i <= 5; $i++) {
            Agency::factory()->create(['name' => "Paginacion Test Sede {$i}", 'status' => AgencyStatus::Active]);
        }

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?agencia=Paginacion+Test&per_page=2&page=2');

        $response->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_orden_por_nombre_ascendente(): void
    {
        Agency::factory()->create(['name' => 'Orden Test Zeta Sede', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Orden Test Alfa Sede', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Orden Test Beta Sede', 'status' => AgencyStatus::Active]);

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?agencia=Orden+Test&sort=name&direction=asc&per_page=10');

        $response->assertOk();
        $names = array_column($response->json('data'), 'agencia');
        $this->assertSame(['Orden Test Alfa Sede', 'Orden Test Beta Sede', 'Orden Test Zeta Sede'], $names);
    }

    public function test_excluye_agencias_no_seleccionables_al_filtrar_por_estado_active(): void
    {
        $active = Agency::factory()->create(['name' => 'Exclusion Test Sede Activa', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Exclusion Test Sede Inactiva', 'status' => AgencyStatus::Inactive]);
        Agency::factory()->create(['name' => 'Exclusion Test Sede Cerrada Temporalmente', 'status' => AgencyStatus::TemporarilyClosed]);
        Agency::factory()->create(['name' => 'Exclusion Test Sede En Revision', 'status' => AgencyStatus::UnderReview]);
        Agency::factory()->create([
            'name' => 'Exclusion Test Sede Trasladada',
            'status' => AgencyStatus::Moved,
            'has_moved' => true,
            'moved_to_agency_id' => $active->id,
        ]);

        $response = $this->withToken($this->token(['agencias:consultar']))
            ->getJson('/api/v1/agencias?agencia=Exclusion+Test&estado=active&per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.agencia', 'Exclusion Test Sede Activa');
    }

    /** @param list<string> $abilities */
    private function token(array $abilities): string
    {
        return User::factory()->create()->createToken('Prueba API Agencias', $abilities)->plainTextToken;
    }
}
