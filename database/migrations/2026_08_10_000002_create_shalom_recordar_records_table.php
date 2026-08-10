<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shalom_recordar_records')) {
            return;
        }

        Schema::create('shalom_recordar_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installation_id')->constrained('shalom_recordar_installations')->cascadeOnDelete();
            $table->uuid('installation_uuid');
            $table->string('external_record_id', 120)->nullable();
            $table->string('record_hash', 64);
            $table->string('field', 100);
            $table->text('value');
            $table->timestampTz('recorded_at')->nullable();
            $table->string('sync_cursor', 120)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->unique(['installation_id', 'record_hash']);
            $table->index(['user_id', 'recorded_at']);
            $table->index(['installation_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shalom_recordar_records');
    }
};
