<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_event_id')->unique();
            $table->string('event_type', 60);
            $table->string('provider_message_id')->nullable()->index();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_webhook_events');
    }
};
