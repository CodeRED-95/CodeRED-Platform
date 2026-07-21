<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubigeos', function (Blueprint $table): void {
            $table->id();
            $table->char('codigo', 6)->unique();
            $table->string('departamento', 120);
            $table->string('provincia', 120);
            $table->string('distrito', 120);
            $table->timestamps();
        });
        Schema::table('ruc_imports', function (Blueprint $table): void {
            $table->unsignedBigInteger('resolved_ubigeo_rows')->default(0);
            $table->unsignedBigInteger('unknown_ubigeo_rows')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ruc_imports', function (Blueprint $table): void {
            $table->dropColumn(['resolved_ubigeo_rows', 'unknown_ubigeo_rows']);
        });
        Schema::dropIfExists('ubigeos');
    }
};
