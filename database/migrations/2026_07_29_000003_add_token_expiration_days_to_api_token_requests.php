<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_token_requests', 'requested_token_expires_in_days')) {
                $table->unsignedSmallInteger('requested_token_expires_in_days')->nullable()->after('requested_expires_in_minutes');
            }

            if (! Schema::hasColumn('api_token_requests', 'token_expires_in_days')) {
                $table->unsignedSmallInteger('token_expires_in_days')->nullable()->after('requested_token_expires_in_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('api_token_requests', 'token_expires_in_days')) {
                $table->dropColumn('token_expires_in_days');
            }

            if (Schema::hasColumn('api_token_requests', 'requested_token_expires_in_days')) {
                $table->dropColumn('requested_token_expires_in_days');
            }
        });
    }
};
