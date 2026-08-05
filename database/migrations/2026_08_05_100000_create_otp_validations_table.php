<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_validations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_token_request_id')->nullable();
            $table->foreign('api_token_request_id')->references('id')->on('api_token_requests')->onDelete('cascade');

            // Email blind index (para públicos sin user_id)
            $table->string('email_blind_index')->nullable();

            // OTP code (hashed con bcrypt)
            $table->string('code_hash');

            // Validación y expiración
            $table->timestamp('expires_at');
            $table->timestamp('validated_at')->nullable();

            // Intentos y reenvíos
            $table->integer('attempts_count')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->integer('resends_count')->default(0);
            $table->integer('max_resends')->default(3);

            // Auditoría
            $table->string('requested_ip')->nullable();
            $table->text('requested_user_agent')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_attempt_ip')->nullable();

            $table->timestamps();

            // Índices para búsqueda rápida
            $table->index('api_token_request_id');
            $table->index('email_blind_index');
            $table->index('expires_at');
            $table->index('validated_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_validations');
    }
};
