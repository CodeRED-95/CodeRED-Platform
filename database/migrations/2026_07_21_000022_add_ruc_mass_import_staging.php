<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruc_imports', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_byte_offset')->default(0);
            $table->unsignedBigInteger('current_line_number')->default(0);
            $table->unsignedInteger('last_completed_chunk')->default(0);
            $table->unsignedBigInteger('address_rows')->default(0);
            $table->string('strategy', 30)->default('insert_ignore');
            $table->string('archive_path', 500)->nullable();
        });
        Schema::create('ruc_staging', function (Blueprint $table): void {
            $table->foreignId('import_id')->constrained('ruc_imports')->cascadeOnDelete();
            $table->unsignedBigInteger('row_number');
            $table->string('ruc', 11);
            $table->text('razon_social');
            $table->string('estado', 60)->nullable();
            $table->string('condicion', 60)->nullable();
            $table->string('ubigeo', 6)->nullable();
            $table->string('departamento', 120)->nullable();
            $table->string('provincia', 120)->nullable();
            $table->string('distrito', 120)->nullable();
            $table->text('direccion')->nullable();
            $table->timestamps();
            $table->primary(['import_id', 'row_number']);
            $table->index(['import_id', 'ruc']);
        });
        if (DB::getDriverName() === 'pgsql' && config('ruc.import.staging_unlogged')) {
            DB::statement('ALTER TABLE ruc_staging SET UNLOGGED');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ruc_staging');
        Schema::table('ruc_imports', function (Blueprint $table): void {
            $table->dropColumn(['current_byte_offset', 'current_line_number', 'last_completed_chunk', 'address_rows', 'strategy', 'archive_path']);
        });
    }
};
