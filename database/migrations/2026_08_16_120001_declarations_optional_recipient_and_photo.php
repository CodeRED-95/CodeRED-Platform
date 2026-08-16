<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El destinatario deja de ser obligatorio y la declaración puede llevar una
 * foto del DNI.
 *
 * Quien envía un paquete no siempre sabe a quién se lo va a recoger, y el
 * formato oficial admite esos campos en blanco: el documento se emite igual.
 * Hasta ahora la base lo impedía con dos columnas NOT NULL, así que la app
 * tenía que inventarse valores para poder guardar.
 *
 * `sede_destino` pasa de 150 a 255 porque ahora guarda la ubicación completa
 * —departamento / provincia / distrito / agencia— y no sólo el nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table): void {
            $table->string('destinatario_dni', 12)->nullable()->change();
            $table->string('destinatario_nombre', 150)->nullable()->change();
            $table->string('sede_destino', 255)->change();

            // Ruta en el disco privado, junto al PDF. Se conserva porque el
            // endpoint de descarga regenera el documento cuando falta, y sin la
            // imagen el PDF apaisado no se podría reconstruir.
            $table->string('foto_dni_path', 255)->nullable()->after('pdf_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table): void {
            $table->dropColumn('foto_dni_path');
            $table->string('sede_destino', 150)->change();
            $table->string('destinatario_nombre', 150)->nullable(false)->change();
            $table->string('destinatario_dni', 12)->nullable(false)->change();
        });
    }
};
