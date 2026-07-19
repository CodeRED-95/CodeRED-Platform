<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->unsignedBigInteger('external_id')->nullable()->after('id');
            $table->text('texto_chosen_terrestre')->nullable()->after('source_text');
            $table->text('texto_chosen_aereo')->nullable()->after('texto_chosen_terrestre');
        });

        DB::statement('CREATE UNIQUE INDEX agencies_external_id_unique ON agencies (external_id) WHERE external_id IS NOT NULL');
        DB::statement("UPDATE agencies SET external_id = source_reference::bigint WHERE external_id IS NULL AND source_reference ~ '^[0-9]+$' AND source_reference::numeric BETWEEN 1 AND 9223372036854775807 AND NOT EXISTS (SELECT 1 FROM agencies duplicate WHERE duplicate.id <> agencies.id AND duplicate.source_reference = agencies.source_reference AND duplicate.source_reference ~ '^[0-9]+$')");
        DB::statement("UPDATE agencies SET texto_chosen_terrestre = source_text WHERE source_text IS NOT NULL AND unaccent(upper(source_text)) LIKE '%TERRESTRE%' AND unaccent(upper(source_text)) NOT LIKE '%AEREO%'");
        DB::statement("UPDATE agencies SET texto_chosen_aereo = source_text WHERE source_text IS NOT NULL AND unaccent(upper(source_text)) LIKE '%AEREO%' AND unaccent(upper(source_text)) NOT LIKE '%TERRESTRE%'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS agencies_external_id_unique');

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn(['external_id', 'texto_chosen_terrestre', 'texto_chosen_aereo']);
        });
    }
};
