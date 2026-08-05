<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make telegram_user_id and telegram_chat_id nullable for public token requests
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN telegram_user_id DROP NOT NULL');
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN telegram_chat_id DROP NOT NULL');
    }

    public function down(): void
    {
        // This is a data-altering migration, so down() won't work for existing data
        // Just rollback by recreating the constraint
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN telegram_user_id SET NOT NULL');
        DB::statement('ALTER TABLE api_token_requests ALTER COLUMN telegram_chat_id SET NOT NULL');
    }
};
