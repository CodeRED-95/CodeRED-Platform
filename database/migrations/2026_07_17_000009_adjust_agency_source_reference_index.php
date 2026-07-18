<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS agencies_source_reference_unique');
        DB::statement("CREATE UNIQUE INDEX agencies_github_gist_source_reference_unique ON agencies (source, source_reference) WHERE source = 'github_gist' AND source_reference IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS agencies_github_gist_source_reference_unique');
        DB::statement('CREATE UNIQUE INDEX agencies_source_reference_unique ON agencies (source, source_reference)');
    }
};
