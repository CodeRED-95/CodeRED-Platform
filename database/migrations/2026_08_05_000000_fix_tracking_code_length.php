<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL requires ALTER COLUMN TYPE with USING clause for string length changes
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN tracking_code TYPE varchar(20)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN tracking_code TYPE varchar(12)');
    }
};
