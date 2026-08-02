<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 120);
            $table->string('aggregate_type', 120);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('destination', 500);
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
