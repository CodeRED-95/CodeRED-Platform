<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruc_records', function (Blueprint $table): void {
            // Permite acotar el rollback de una importación a únicamente los
            // registros que ella insertó/actualizó, en vez de borrar por
            // ventana de tiempo (que podía arrastrar registros de otras
            // importaciones o inserciones concurrentes).
            if (! Schema::hasColumn('ruc_records', 'ruc_import_id')) {
                $table->foreignId('ruc_import_id')->nullable()->after('id')->constrained('ruc_imports')->nullOnDelete();
                $table->index('ruc_import_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ruc_records', function (Blueprint $table): void {
            if (Schema::hasColumn('ruc_records', 'ruc_import_id')) {
                $table->dropForeignIdFor('ruc_imports', 'ruc_import_id');
                $table->dropColumn('ruc_import_id');
            }
        });
    }
};
