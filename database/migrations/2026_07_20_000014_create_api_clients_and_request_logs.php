<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->timestamp('revoked_at')->nullable()->after('expires_at')->index();
        });
        Schema::create('revoked_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('original_token_id')->index();
            $table->string('name');
            $table->string('owner_name');
            $table->jsonb('abilities');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('service', 20);
            $table->string('endpoint', 255);
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('identifier_hash', 64)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['service', 'created_at']);
            $table->index(['status_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('revoked_api_tokens');
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex(['revoked_at']);
            $table->dropColumn('revoked_at');
        });
        Schema::dropIfExists('api_clients');
    }
};
