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
            $table->string('nombres');
            $table->string('apellido_paterno');
            $table->string('apellido_materno');
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedSmallInteger('edad')->nullable();
            $table->string('source', 20)->default('internal');
            $table->string('provider_reference')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
        Schema::table('api_request_logs', function (Blueprint $table): void {
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
            $table->dropColumn(['source', 'provider_called', 'provider_status_code', 'cache_hit', 'local_database_hit']);
        });
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->dropColumn(['is_encrypted', 'created_at', 'updated_at']);
        });
        Schema::dropIfExists('dni_records');
    }
};
