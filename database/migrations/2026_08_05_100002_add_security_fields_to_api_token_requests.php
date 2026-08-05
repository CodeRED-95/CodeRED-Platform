<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table) {
            // OTP validación (para solicitudes públicas)
            $table->timestamp('otp_validated_at')->nullable()->after('delivery_metadata');

            // Contadores para single-use enforcement
            $table->integer('token_reveal_count')->default(0)->after('otp_validated_at');
            $table->integer('protected_data_view_count')->default(0)->after('token_reveal_count');

            // Última visualización de datos protegidos (para auditoría)
            $table->string('last_protected_view_ip')->nullable()->after('protected_data_view_count');
            $table->timestamp('last_protected_view_at')->nullable()->after('last_protected_view_ip');

            // Índices para búsquedas
            $table->index('otp_validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table) {
            $table->dropIndex(['otp_validated_at']);
            $table->dropColumn([
                'otp_validated_at',
                'token_reveal_count',
                'protected_data_view_count',
                'last_protected_view_ip',
                'last_protected_view_at',
            ]);
        });
    }
};
