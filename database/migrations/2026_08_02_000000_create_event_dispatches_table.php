<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id', 40)->unique();
            $table->string('type', 120)->index();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->jsonb('payload');
            $table->timestampTz('occurred_at');
            $table->string('tenant', 80)->default('default');
            $table->string('source', 80)->default('platform');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_dispatches');
    }
};
