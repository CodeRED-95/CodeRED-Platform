<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agencies')) {
            return;
        }

        DB::statement('ALTER TABLE agencies ALTER COLUMN code DROP NOT NULL');
        DB::statement('ALTER TABLE agencies ALTER COLUMN department DROP NOT NULL');
        DB::statement('ALTER TABLE agencies ALTER COLUMN province DROP NOT NULL');
        DB::statement('ALTER TABLE agencies ALTER COLUMN district DROP NOT NULL');
        DB::statement('ALTER TABLE agencies ALTER COLUMN address DROP NOT NULL');
        DB::statement('ALTER TABLE agencies ALTER COLUMN latitude TYPE numeric(15,12) USING latitude::numeric');
        DB::statement('ALTER TABLE agencies ALTER COLUMN longitude TYPE numeric(15,12) USING longitude::numeric');

        foreach ([
            'department' => 'agencies_department_idx',
            'province' => 'agencies_province_idx',
            'district' => 'agencies_district_idx',
            'status' => 'agencies_status_idx',
        ] as $column => $index) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$index} ON agencies ({$column})");
        }

        $duplicates = DB::table('agencies')->select('external_id', DB::raw('count(*) total'))
            ->whereNotNull('external_id')->groupBy('external_id')->havingRaw('count(*) > 1')->count();

        if ($duplicates === 0) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS agencies_external_id_unique_safe ON agencies (external_id) WHERE external_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS agencies_external_id_unique_safe');
        DB::statement('ALTER TABLE agencies ALTER COLUMN latitude TYPE numeric(10,7) USING round(latitude, 7)');
        DB::statement('ALTER TABLE agencies ALTER COLUMN longitude TYPE numeric(10,7) USING round(longitude, 7)');
    }
};
