<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make all telegram-related and request-specific fields nullable for public token requests
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN requested_expires_in_minutes DROP NOT NULL');
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN requested_token_name DROP NOT NULL');
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN requested_abilities SET DEFAULT \'[]\'');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN requested_expires_in_minutes SET NOT NULL');
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN requested_token_name SET NOT NULL');
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN requested_abilities SET DEFAULT NULL');
    }
};
