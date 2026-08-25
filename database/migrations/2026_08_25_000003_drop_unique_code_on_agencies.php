<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La abreviatura deja de ser unica; la identidad es `external_id`.
 *
 * `code` viene de `ter_abrebiatura` de Shalom, que NO identifica una agencia:
 * `PSC` designa tanto a PISAC (Calca, Cusco) como a PISCO (Ica). El indice
 * unico impedia dar de alta la segunda agencia que compartiera abreviatura, y
 * empujaba al emparejamiento a confundir fichas distintas.
 *
 * `external_id` ya tiene indice unico parcial (`agencies_external_id_unique`),
 * asi que la identidad autoritativa no cambia: solo se retira una restriccion
 * que la realidad de los datos no cumple. Se conserva el indice NO unico sobre
 * `code` para que las busquedas por abreviatura sigan siendo rapidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agencies')) {
            return;
        }

        if ($this->hasUniqueConstraint()) {
            DB::statement('ALTER TABLE agencies DROP CONSTRAINT agencies_code_unique');
        }

        // La busqueda por abreviatura sigue existiendo: sin el unico, hace
        // falta que quede un indice corriente que la sostenga.
        if (! $this->hasIndex('agencies_code_idx')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->index('code', 'agencies_code_idx');
            });
        }
    }

    public function down(): void
    {
        // Reponer el unico solo es posible si no hay abreviaturas repetidas,
        // que es justo lo que esta migracion viene a permitir.
        if (! $this->hasUniqueConstraint() && ! $this->hasDuplicateCodes()) {
            DB::statement('ALTER TABLE agencies ADD CONSTRAINT agencies_code_unique UNIQUE (code)');
        }
    }

    private function hasUniqueConstraint(): bool
    {
        return DB::table('pg_constraint')
            ->where('conrelid', DB::raw("'agencies'::regclass"))
            ->where('conname', 'agencies_code_unique')
            ->exists();
    }

    private function hasIndex(string $name): bool
    {
        return DB::table('pg_indexes')->where('tablename', 'agencies')->where('indexname', $name)->exists();
    }

    private function hasDuplicateCodes(): bool
    {
        return DB::table('agencies')
            ->select('code')
            ->whereNotNull('code')
            ->groupBy('code')
            ->havingRaw('count(*) > 1')
            ->exists();
    }
};
