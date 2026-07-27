<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_token_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->string('telegram_user_id')->index();
            $table->string('telegram_chat_id')->index();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
            $table->string('requested_token_name');
            $table->json('requested_abilities');
            $table->unsignedInteger('requested_expires_in_minutes');
            $table->string('status')->index();
            $table->string('requested_ip')->nullable();
            $table->string('request_source')->default('telegram_n8n');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->text('encrypted_plain_text_token')->nullable();
            $table->string('delivery_status')->default('not_available')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedInteger('delivery_attempts')->default(0);
            $table->string('delivery_reference')->nullable();
            $table->timestamp('result_retrieved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_requests');
    }
};
