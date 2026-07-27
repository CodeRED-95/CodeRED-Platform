<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('integration_uuid')->unique();
            $table->string('provider')->index();
            $table->string('instance_name');
            $table->string('instance_url', 500)->nullable();
            $table->string('hostname')->nullable();
            $table->string('environment')->nullable()->index();
            $table->string('version')->nullable();
            $table->string('status')->default('connected')->index();
            $table->text('encrypted_secret');
            $table->json('ip_allowlist')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedBigInteger('uptime')->nullable();
            $table->unsignedInteger('running_workflows')->nullable();
            $table->unsignedInteger('memory_usage')->nullable();
            $table->unsignedInteger('cpu_usage')->nullable();
            $table->timestamp('secret_rotated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('integration_pairings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('pair_uuid')->unique();
            $table->string('provider')->default('n8n')->index();
            $table->string('pair_code')->unique();
            $table->text('encrypted_temporary_secret');
            $table->string('nonce');
            $table->string('status')->default('pending')->index();
            $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('integration_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('capability');
            $table->string('service')->nullable()->index();
            $table->string('method', 16)->default('POST');
            $table->string('path', 500);
            $table->string('version')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_seen')->nullable();
            $table->string('checksum', 64);
            $table->timestamps();
            $table->unique(['integration_id', 'capability']);
        });

        Schema::create('integration_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('service');
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'service']);
        });

        Schema::create('integration_plugins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('plugin_id');
            $table->string('name');
            $table->string('version')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'plugin_id']);
        });

        Schema::create('integration_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->nullable()->constrained('integrations')->cascadeOnDelete();
            $table->string('event')->index();
            $table->string('level')->default('info')->index();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_plugins');
        Schema::dropIfExists('integration_services');
        Schema::dropIfExists('integration_capabilities');
        Schema::dropIfExists('integration_pairings');
        Schema::dropIfExists('integrations');
    }
};
