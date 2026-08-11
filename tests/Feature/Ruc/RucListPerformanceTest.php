<?php

declare(strict_types=1);

namespace Tests\Feature\Ruc;

use App\Livewire\Admin\Ruc\Records;
use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucRecord;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @group ruc-heavy
 *
 * Contrato de rendimiento del listado del padrón (/admin/ruc), pensado para
 * 18M+ filas.
 *
 * Se prueba el componente con Livewire::test() y no con una petición HTTP:
 * `admin.ruc.records` es un componente Livewire de página completa, así que la
 * respuesta HTTP es el layout y `assertViewHas('records')` nunca puede ver los
 * datos del componente. Esa era la razón de que estas pruebas llevaran tiempo
 * fallando con "The response is not a view".
 */
class RucListPerformanceTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs($this->adminUser());
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    private function seedRecords(int $count, string $estado = 'ACTIVO'): void
    {
        DB::statement(
            "INSERT INTO ruc_records (ruc, razon_social, estado, condicion, ubigeo, departamento, provincia, distrito, direccion, tipo_via, nombre_via, numero, created_at, updated_at)
             SELECT lpad((20000000000 + g)::text, 11, '0'),
                    'EMPRESA ' || g || ' S.A.C.',
                    ?, 'HABIDO', '150101', 'LIMA', 'LIMA', 'MIRAFLORES',
                    'AV. PRUEBA ' || g, 'AV.', 'PRUEBA', g::text, now(), now()
             FROM generate_series(1::bigint, ?::bigint) AS g",
            [$estado, $count]
        );
    }

    /** @return Testable */
    private function listComponent(array $params = [])
    {
        return Livewire::test(Records::class, $params);
    }

    // ------------------------------------------------------- carga inicial ---

    public function test_el_listado_usa_cursor_pagination_y_no_hace_count(): void
    {
        $this->seedRecords(120);

        $records = $this->listComponent()->viewData('records');

        // CursorPaginator, por diseño, no ejecuta COUNT(*): no tiene total().
        $this->assertInstanceOf(CursorPaginator::class, $records);
        $this->assertFalse(method_exists($records, 'total'));
    }

    public function test_la_primera_pagina_trae_como_maximo_50_registros(): void
    {
        $this->seedRecords(200);

        $records = $this->listComponent()->viewData('records');

        $this->assertCount(50, $records->items());
        $this->assertTrue($records->hasMorePages());
    }

    /**
     * La consulta del listado no debe ejecutar ningún COUNT sobre
     * ruc_records: con 18M filas cuesta ~8 s y ningún índice lo evita.
     */
    public function test_ninguna_consulta_del_listado_hace_count_sobre_ruc_records(): void
    {
        $this->seedRecords(120);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->listComponent()->viewData('records');

        $counts = array_filter(
            $queries,
            static fn (string $sql): bool => str_contains(strtolower($sql), 'count(')
                && str_contains(strtolower($sql), 'ruc_records')
        );

        $this->assertSame([], array_values($counts), 'El listado no debe ejecutar COUNT(*) sobre ruc_records.');
    }

    public function test_el_total_del_padron_sale_de_ruc_statistics_no_de_un_count(): void
    {
        $this->seedRecords(10);

        // DatabaseTruncation vacía también ruc_statistics, así que la fila
        // única de metadatos hay que recrearla en lugar de actualizarla.
        DB::table('ruc_statistics')->delete();
        DB::table('ruc_statistics')->insert([
            'total_records' => 18000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        cache()->forget('ruc:records:count');

        $total = $this->listComponent()->viewData('totalRecords');

        // Se muestra el metadato, no las 10 filas realmente presentes.
        $this->assertSame(18000000, $total);
    }

    // ------------------------------------------------------------ columnas ---

    public function test_el_listado_solo_selecciona_las_columnas_visibles(): void
    {
        $this->seedRecords(1);

        $first = $this->listComponent()->viewData('records')->items()[0];
        $loaded = array_keys($first->getAttributes());

        sort($loaded);
        $this->assertSame([
            'condicion', 'departamento', 'direccion', 'distrito', 'estado',
            'id', 'provincia', 'razon_social', 'ruc', 'ubigeo',
        ], $loaded);

        // Las columnas del desglose de dirección se cargan solo en el detalle.
        foreach (['tipo_via', 'nombre_via', 'numero', 'created_at'] as $absent) {
            $this->assertNotContains($absent, $loaded, "El listado no debe traer {$absent}.");
        }
    }

    public function test_el_detalle_si_carga_las_columnas_adicionales(): void
    {
        $this->seedRecords(1);
        $record = RucRecord::query()->firstOrFail();

        $detail = RucRecord::query()->findOrFail($record->id);

        $this->assertSame('AV.', $detail->tipo_via);
        $this->assertSame('PRUEBA', $detail->nombre_via);
        $this->assertNotNull($detail->created_at);
    }

    // ------------------------------------------------------------ búsqueda ---

    public function test_la_busqueda_por_ruc_exacto_devuelve_una_sola_fila(): void
    {
        $this->seedRecords(500);
        $ruc = RucRecord::query()->orderBy('id')->value('ruc');

        $records = $this->listComponent(['search' => $ruc])->viewData('records');

        $this->assertCount(1, $records->items());
        $this->assertSame($ruc, $records->items()[0]->ruc);
    }

    /** El RUC exacto debe resolverse con igualdad sobre el índice único. */
    public function test_la_busqueda_por_ruc_exacto_usa_igualdad_no_like(): void
    {
        $this->seedRecords(10);
        $ruc = RucRecord::query()->orderBy('id')->value('ruc');

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->listComponent(['search' => $ruc])->viewData('records');

        $listQuery = collect($queries)->first(
            static fn (string $sql): bool => str_contains($sql, 'from "ruc_records"')
        );

        $this->assertNotNull($listQuery);
        $this->assertStringContainsString('"ruc" = ?', $listQuery);
        $this->assertStringNotContainsString('ilike', $listQuery, 'Un RUC exacto no debe buscarse con ILIKE.');
    }

    public function test_la_busqueda_por_razon_social_exige_tres_caracteres(): void
    {
        $this->seedRecords(5);

        foreach (['E', 'EM'] as $tooShort) {
            $this->assertNotNull(
                $this->listComponent(['search' => $tooShort])->viewData('searchError'),
                "Con '{$tooShort}' debe avisarse de que la búsqueda es demasiado amplia."
            );
        }

        $this->assertNull($this->listComponent(['search' => 'EMPRESA'])->viewData('searchError'));
    }

    public function test_la_busqueda_por_razon_social_usa_ilike_con_trigram(): void
    {
        $this->seedRecords(5);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $records = $this->listComponent(['search' => 'EMPRESA 3 S.A.C.'])->viewData('records');

        $listQuery = collect($queries)->first(
            static fn (string $sql): bool => str_contains($sql, 'from "ruc_records"')
        );

        $this->assertStringContainsString('ilike', (string) $listQuery);
        $this->assertGreaterThan(0, $records->count());
    }

    // ------------------------------------------------------------- filtros ---

    public function test_los_filtros_estan_hardcodeados_y_no_hacen_distinct(): void
    {
        $this->seedRecords(50);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $component = $this->listComponent(['estado' => 'ACTIVO']);

        $this->assertIsArray($component->viewData('estados'));
        $this->assertIsArray($component->viewData('condiciones'));

        $distinct = array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'distinct') && str_contains($sql, 'ruc_records')
        );

        $this->assertSame([], array_values($distinct), 'Los desplegables no deben salir de un DISTINCT sobre la tabla.');
    }

    public function test_los_filtros_acotan_el_resultado(): void
    {
        $this->seedRecords(30, 'ACTIVO');
        $this->seedRecords(0);
        DB::statement("UPDATE ruc_records SET estado = 'SUSPENDIDO' WHERE id % 3 = 0");

        $activos = $this->listComponent(['estado' => 'ACTIVO'])->viewData('records');

        $this->assertGreaterThan(0, $activos->count());
        foreach ($activos->items() as $item) {
            $this->assertSame('ACTIVO', $item->estado);
        }
    }

    // -------------------------------------------------------------- cursor ---

    public function test_el_cursor_avanza_sin_repetir_ni_saltarse_registros(): void
    {
        $this->seedRecords(120);

        $page1 = $this->listComponent()->viewData('records');
        $this->assertCount(50, $page1->items());

        $page2 = $this->listComponent(['cursor' => $page1->nextCursor()->encode()])->viewData('records');
        $this->assertCount(50, $page2->items());

        $ids1 = collect($page1->items())->pluck('id')->all();
        $ids2 = collect($page2->items())->pluck('id')->all();

        $this->assertEmpty(array_intersect($ids1, $ids2), 'Las páginas no deben solaparse.');
        $this->assertSame(max($ids1) + 1, min($ids2), 'El cursor no debe saltarse registros.');
    }

    public function test_cambiar_un_filtro_reinicia_el_cursor(): void
    {
        $this->seedRecords(120);

        $page1 = $this->listComponent()->viewData('records');

        $component = $this->listComponent(['cursor' => $page1->nextCursor()->encode()]);
        $this->assertNotNull($component->get('cursor'));

        $component->set('estado', 'ACTIVO');

        $this->assertNull($component->get('cursor'), 'Al cambiar un filtro el cursor debe volver al principio.');
    }
}
