<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shalom_delivery_records')) {
            return;
        }
        Schema::create('shalom_delivery_records', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->index();
            $table->string('field', 50); // DNI, CE, RUC, OS, Clave
            $table->text('value');
            $table->timestamp('timestamp'); // Timestamp original de captura en Chrome
            $table->uuid('sync_batch_id')->nullable()->index(); // Agrupar registros por sincronización
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Enlace a usuario CodeRED si existe
            $table->timestamps();

            // Índices
            $table->index(['username', 'created_at']);
            $table->index(['field', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shalom_delivery_records');
    }
};
