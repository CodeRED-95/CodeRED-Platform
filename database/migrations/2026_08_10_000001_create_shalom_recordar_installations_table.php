<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shalom_recordar_installations')) {
            return;
        }

        Schema::create('shalom_recordar_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('installation_uuid');
            $table->string('extension_version', 40);
            $table->string('device_name', 120)->nullable();
            $table->string('browser_name', 80)->nullable();
            $table->string('browser_version', 40)->nullable();
            $table->string('platform_name', 80)->nullable();
            $table->string('platform_version', 40)->nullable();
            $table->string('last_sync_cursor', 120)->nullable();
            $table->string('last_sync_hash', 64)->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'installation_uuid']);
            $table->index(['user_id', 'last_synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shalom_recordar_installations');
    }
};
