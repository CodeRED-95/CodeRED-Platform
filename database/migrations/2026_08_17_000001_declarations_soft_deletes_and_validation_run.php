<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos protecciones para un documento que es, por naturaleza, histórico.
 *
 * `deleted_at`: una declaración jurada es un documento legal. Hoy no existe
 * ningún camino en la aplicación que la borre —ni endpoint, ni panel, ni
 * comando—, y precisamente por eso conviene que, el día que exista, no destruya
 * nada de forma irreversible por descuido. Ver docs/DECLARACIONES_SEGURIDAD.md
 * para lo que esta medida sí cubre y lo que no.
 *
 * `validation_run`: identifica los registros creados por una validación de
 * extremo a extremo contra el entorno real. Existe para que limpiarlos después
 * sea una operación exacta —"borra lo de esta ejecución"— en lugar de una
 * conjetura sobre rangos de identificadores, que es lo que provocó la pérdida
 * de una declaración real el 16/08/2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table): void {
            $table->softDeletes();

            $table->uuid('validation_run')->nullable()->after('foto_dni_path');
            $table->index('validation_run');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table): void {
            $table->dropIndex(['validation_run']);
            $table->dropColumn('validation_run');
            $table->dropSoftDeletes();
        });
    }
};
