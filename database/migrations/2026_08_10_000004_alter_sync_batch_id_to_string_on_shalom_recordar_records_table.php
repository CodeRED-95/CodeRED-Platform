<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shalom_recordar_records') || ! Schema::hasColumn('shalom_recordar_records', 'sync_batch_id')) {
            return;
        }

        DB::statement('ALTER TABLE shalom_recordar_records ALTER COLUMN sync_batch_id TYPE varchar(120) USING sync_batch_id::text');
    }

    public function down(): void
    {
        if (! Schema::hasTable('shalom_recordar_records') || ! Schema::hasColumn('shalom_recordar_records', 'sync_batch_id')) {
            return;
        }

        DB::statement('ALTER TABLE shalom_recordar_records ALTER COLUMN sync_batch_id TYPE uuid USING NULLIF(sync_batch_id, \'\')::uuid');
    }
};
