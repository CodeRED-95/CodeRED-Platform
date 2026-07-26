<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            if (! Schema::hasColumn('agencies', 'ubigeo_id')) {
                $table->foreignId('ubigeo_id')->nullable()->after('district')->constrained('ubigeos')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            if (Schema::hasColumn('agencies', 'ubigeo_id')) {
                $table->dropConstrainedForeignId('ubigeo_id');
            }
        });
    }
};
