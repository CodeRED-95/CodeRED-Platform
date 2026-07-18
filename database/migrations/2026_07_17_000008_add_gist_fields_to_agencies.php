<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->text('source_text')->nullable()->after('source_reference');
            $table->text('map_url')->nullable()->after('source_text');
            $table->string('size')->nullable()->after('map_url');
            $table->boolean('is_co')->default(false)->after('size');
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->unique(['source', 'source_reference'], 'agencies_source_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropUnique('agencies_source_reference_unique');
            $table->dropColumn(['source_text', 'map_url', 'size', 'is_co']);
        });
    }
};
