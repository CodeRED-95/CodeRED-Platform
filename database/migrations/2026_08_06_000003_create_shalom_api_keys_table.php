<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shalom_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); // Nombre descriptivo (ej: "Extensión Chrome")
            $table->string('key_hash'); // Hash SHA-256 de la clave
            $table->string('key_prefix', 20)->unique(); // Primeros 20 chars en plaintext (para logging)
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Usuario propietario
            $table->integer('requests_count')->default(0); // Contador de solicitudes
            $table->timestamp('last_used_at')->nullable(); // Última uso
            $table->timestamp('revoked_at')->nullable(); // Si fue revocada
            $table->timestamps();

            // Índices
            $table->index('key_prefix');
            $table->index(['user_id', 'revoked_at']);
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shalom_api_keys');
    }
};
