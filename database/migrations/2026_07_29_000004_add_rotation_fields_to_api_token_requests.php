<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('personal_access_tokens', 'revoked_by')) {
                $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('personal_access_tokens', 'revocation_reason')) {
                $table->string('revocation_reason', 80)->nullable()->after('revoked_by');
            }
        });

        Schema::table('api_token_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_token_requests', 'request_type')) {
                $table->string('request_type', 20)->default('issuance')->after('request_uuid')->index();
            }

            if (! Schema::hasColumn('api_token_requests', 'source_personal_access_token_id')) {
                $table->foreignId('source_personal_access_token_id')->nullable()->after('personal_access_token_id')->constrained('personal_access_tokens')->nullOnDelete();
            }

            if (! Schema::hasColumn('api_token_requests', 'replacement_personal_access_token_id')) {
                $table->foreignId('replacement_personal_access_token_id')->nullable()->after('source_personal_access_token_id')->constrained('personal_access_tokens')->nullOnDelete();
            }

            if (! Schema::hasColumn('api_token_requests', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable()->after('request_source');
                $table->unique(['request_type', 'source_personal_access_token_id', 'idempotency_key'], 'api_token_requests_rotation_idempotency_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('api_token_requests', 'idempotency_key')) {
                $table->dropUnique('api_token_requests_rotation_idempotency_unique');
                $table->dropColumn('idempotency_key');
            }

            if (Schema::hasColumn('api_token_requests', 'replacement_personal_access_token_id')) {
                $table->dropConstrainedForeignId('replacement_personal_access_token_id');
            }

            if (Schema::hasColumn('api_token_requests', 'source_personal_access_token_id')) {
                $table->dropConstrainedForeignId('source_personal_access_token_id');
            }

            if (Schema::hasColumn('api_token_requests', 'request_type')) {
                $table->dropColumn('request_type');
            }
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            if (Schema::hasColumn('personal_access_tokens', 'revocation_reason')) {
                $table->dropColumn('revocation_reason');
            }

            if (Schema::hasColumn('personal_access_tokens', 'revoked_by')) {
                $table->dropConstrainedForeignId('revoked_by');
            }
        });
    }
};
