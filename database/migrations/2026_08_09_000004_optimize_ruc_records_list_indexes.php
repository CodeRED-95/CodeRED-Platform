<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices del listado del padrón, ajustados a la forma REAL de la consulta:
 *
 *     WHERE <filtro> = ?  ORDER BY id  LIMIT 51
 *
 * Un índice de una sola columna no sirve para esa consulta: PostgreSQL puede
 * usarlo para localizar las filas, pero después tiene que ordenarlas por id,
 * así que en la práctica prefiere recorrer la clave primaria y descartar lo
 * que no casa. Con 18M filas y un filtro selectivo eso significa recorrer
 * millones de filas para encontrar 51.
 *
 * Un índice compuesto (columna, id) SÍ sirve: las filas de cada valor ya
 * salen ordenadas por id, de modo que el escaneo se detiene a las 51.
 *
 * MEDIDO sobre 18 000 000 de filas reales (EXPLAIN ANALYZE, ver
 * docs-ruc/LIST_PERFORMANCE.md):
 *
 *   provincia + condicion    10 943 ms  ->    0.31 ms
 *   distrito + estado         1 131 ms  ->    2.46 ms
 *   ubigeo + departamento       792 ms  ->    8.09 ms
 *   provincia (solo)             97 ms  ->    9.83 ms
 *   distrito (solo)             106 ms  ->    1.48 ms
 *
 * QUÉ SE ELIMINA Y POR QUÉ. Los seis índices de una sola columna se retiran:
 *
 *   - estado (4 valores), condicion (3), departamento (8): ningún valor es lo
 *     bastante selectivo para que el planificador los elija. Se comprobó que
 *     no se usaban (idx_scan = 0) y que sin ellos las consultas siguen en
 *     torno a 1-9 ms, porque el recorrido de la clave primaria encuentra 51
 *     coincidencias enseguida cuando el filtro es tan común.
 *   - provincia (195), distrito (1874), ubigeo (1874): se sustituyen por su
 *     versión compuesta con id.
 *
 * COSTE. Los compuestos ocupan más porque añadir id hace único cada valor y
 * PostgreSQL ya no puede deduplicar entradas repetidas: el total de índices
 * pasa de ~2.5 GB a ~3.7 GB con 18M filas. Es el precio de convertir un peor
 * caso de 11 segundos en uno de 10 milisegundos.
 *
 * No toca los datos: CREATE INDEX / DROP INDEX solo afectan a estructuras
 * auxiliares. ruc_records se conserva intacta.
 */
return new class extends Migration
{
    /** Columnas filtrables cuya cardinalidad justifica un índice (columna, id). */
    private const COMPOSITE_INDEXES = [
        'ruc_records_provincia_id_index' => 'provincia',
        'ruc_records_distrito_id_index' => 'distrito',
        'ruc_records_ubigeo_id_index' => 'ubigeo',
    ];

    /** Índices de una sola columna que el planificador no aprovecha. */
    private const OBSOLETE_INDEXES = [
        'ruc_records_estado_index',
        'ruc_records_condicion_index',
        'ruc_records_departamento_index',
        'ruc_records_provincia_index',
        'ruc_records_distrito_index',
        'ruc_records_ubigeo_index',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ruc_records')) {
            return;
        }

        // Primero se crean los sustitutos: si el proceso se interrumpiera
        // entre medias, la tabla nunca se queda sin ningún índice de filtro.
        foreach (self::COMPOSITE_INDEXES as $name => $column) {
            DB::statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON ruc_records (%s, id)',
                $name,
                $column
            ));
        }

        foreach (self::OBSOLETE_INDEXES as $name) {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s', $name));
        }

        // Sin esto el planificador sigue con las estadísticas anteriores y
        // puede tardar en elegir los índices nuevos.
        DB::statement('ANALYZE ruc_records');
    }

    public function down(): void
    {
        if (! Schema::hasTable('ruc_records')) {
            return;
        }

        foreach (self::OBSOLETE_INDEXES as $name) {
            $column = str_replace(['ruc_records_', '_index'], '', $name);
            DB::statement(sprintf('CREATE INDEX IF NOT EXISTS %s ON ruc_records (%s)', $name, $column));
        }

        foreach (array_keys(self::COMPOSITE_INDEXES) as $name) {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s', $name));
        }

        DB::statement('ANALYZE ruc_records');
    }
};
