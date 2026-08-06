<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shalom_delivery_records', function (Blueprint $table): void {
            // Agregar FK a shalom_api_keys para tracking de qué clave envió el registro
            if (!Schema::hasColumn('shalom_delivery_records', 'shalom_api_key_id')) {
                $table->foreignId('shalom_api_key_id')->nullable()->after('user_id')->constrained('shalom_api_keys')->nullOnDelete();
            }
            // Agregar IP para auditoría
            if (!Schema::hasColumn('shalom_delivery_records', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('shalom_api_key_id');
            }
            // Agregar user_agent para auditoría
            if (!Schema::hasColumn('shalom_delivery_records', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shalom_delivery_records', function (Blueprint $table): void {
            if (Schema::hasColumn('shalom_delivery_records', 'user_agent')) {
                $table->dropColumn('user_agent');
            }
            if (Schema::hasColumn('shalom_delivery_records', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
            if (Schema::hasColumn('shalom_delivery_records', 'shalom_api_key_id')) {
                $table->dropForeignIdFor('shalom_api_keys', 'shalom_api_key_id');
                $table->dropColumn('shalom_api_key_id');
            }
        });
    }
};
