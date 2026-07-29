<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_token_requests', 'requested_token_type')) {
                $table->string('requested_token_type', 40)->nullable()->after('requested_token_name')->index();
            }

            if (! Schema::hasColumn('api_token_requests', 'token_type')) {
                $table->string('token_type', 40)->nullable()->after('requested_token_type')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('api_token_requests', 'token_type')) {
                $table->dropColumn('token_type');
            }

            if (Schema::hasColumn('api_token_requests', 'requested_token_type')) {
                $table->dropColumn('requested_token_type');
            }
        });
    }
};
