<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shalom_recordar_installations')) {
            return;
        }

        Schema::table('shalom_recordar_installations', function (Blueprint $table): void {
            if (! Schema::hasColumn('shalom_recordar_installations', 'sync_token_id')) {
                $table->foreignId('sync_token_id')->nullable()->after('last_sync_hash')->constrained('personal_access_tokens')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shalom_recordar_installations') || ! Schema::hasColumn('shalom_recordar_installations', 'sync_token_id')) {
            return;
        }

        Schema::table('shalom_recordar_installations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sync_token_id');
        });
    }
};
