<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dni_records', function (Blueprint $table): void {
            $table->id();
            $table->string('dni', 8)->unique();
            $table->string('nombre_completo');
            $table->string('nombres', 120);
            $table->string('apellido_paterno', 120);
            $table->string('apellido_materno', 120);
            $table->string('genero', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('codigo_verificacion', 20)->nullable();
            $table->string('source', 20)->default('internal');
            $table->string('provider_reference', 100)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->index('nombre_completo');
        });

        Schema::table('application_settings', function (Blueprint $table): void {
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        Schema::table('api_request_logs', function (Blueprint $table): void {
            $table->string('request_type', 20)->default('api')->index();
            $table->string('source', 20)->nullable();
            $table->boolean('provider_called')->default(false);
            $table->unsignedSmallInteger('provider_status_code')->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->boolean('local_database_hit')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('api_request_logs', function (Blueprint $table): void {
            $table->dropColumn(['request_type', 'source', 'provider_called', 'provider_status_code', 'cache_hit', 'local_database_hit']);
        });
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->dropColumn(['is_encrypted', 'created_at', 'updated_at']);
        });
        Schema::dropIfExists('dni_records');
    }
};
