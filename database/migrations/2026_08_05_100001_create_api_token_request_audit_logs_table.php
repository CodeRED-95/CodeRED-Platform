<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_token_request_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('api_token_request_id');
            $table->foreign('api_token_request_id')->references('id')->on('api_token_requests')->onDelete('cascade');

            // Usuario que realizó la acción (puede ser null para acciones públicas)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Acción realizada (enum style: 'otp_requested', 'otp_verified', 'token_revealed', etc.)
            $table->string('action');

            // Detalles de la acción
            $table->json('details')->nullable();

            // Red e identidad del cliente
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('user_agent_hash')->nullable();

            // Estado antes y después de la acción
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();

            // Auditoría de sensibilidad
            $table->boolean('sensitive_data_revealed')->default(false);
            $table->string('sensitive_data_type')->nullable(); // 'token', 'personal_data', 'otp', etc.

            // Notas internas
            $table->text('notes')->nullable();

            $table->timestamps();

            // Índices para búsqueda rápida
            $table->index('api_token_request_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('ip_address');
            $table->index('created_at');
            $table->index('sensitive_data_revealed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_request_audit_logs');
    }
};
