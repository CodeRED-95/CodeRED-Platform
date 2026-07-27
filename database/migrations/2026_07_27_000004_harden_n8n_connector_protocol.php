<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table): void {
            $table->string('n8n_version')->nullable()->after('version');
            $table->string('connector_version')->nullable()->after('n8n_version');
            $table->string('protocol_version')->default('1.0')->after('connector_version');
            $table->string('last_ip')->nullable()->after('latency_ms');
            $table->timestamp('connected_at')->nullable()->after('last_seen_at');
            $table->timestamp('revoked_at')->nullable()->after('secret_rotated_at');
            $table->text('pending_encrypted_secret')->nullable()->after('encrypted_secret');
            $table->timestamp('pending_secret_expires_at')->nullable()->after('pending_encrypted_secret');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table): void {
            $table->dropColumn(['n8n_version', 'connector_version', 'protocol_version', 'last_ip', 'connected_at', 'revoked_at', 'pending_encrypted_secret', 'pending_secret_expires_at']);
        });
    }
};
