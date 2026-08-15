<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declarations', function (Blueprint $table): void {
            $table->id();

            // Autoría: la declaración pertenece a quien la generó.
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();

            // Referencia viva a la agencia, para poder navegar al catálogo actual.
            // Se anula si la agencia desaparece: el documento sigue siendo válido
            // porque el nombre impreso vive en el snapshot de abajo.
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();

            $table->string('remitente_dni', 12);
            $table->string('remitente_nombre', 150);
            $table->string('remitente_telefono', 30)->nullable();

            $table->string('destinatario_dni', 12);
            $table->string('destinatario_nombre', 150);
            $table->string('destinatario_telefono', 30)->nullable();

            // Snapshot histórico: es el texto que se imprimió como sede de destino.
            // Se guarda aparte de agency_id a propósito — si la agencia cambia de
            // nombre o se traslada, el PDF regenerado debe seguir diciendo lo mismo
            // que el original. Sólo se congela el nombre porque es el único dato de
            // la agencia que aparece en el documento.
            $table->string('sede_destino', 150);

            $table->string('motivo_envio', 255)->nullable();

            // Ruta relativa dentro del disco privado. Nunca se expone al cliente.
            $table->string('pdf_path', 255)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('remitente_dni');
            $table->index('created_at');
        });

        Schema::create('declaration_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declaration_id')->constrained()->cascadeOnDelete();

            // La cantidad es texto en el formulario oficial ("2", "media caja"…),
            // así que se conserva tal cual en lugar de forzarla a entero.
            $table->string('cantidad', 20)->nullable();
            $table->string('descripcion', 255);

            // Preserva el orden en que se declararon los bienes.
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['declaration_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaration_items');
        Schema::dropIfExists('declarations');
    }
};
