<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shalom_recordar_records')) {
            return;
        }

        Schema::table('shalom_recordar_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('shalom_recordar_records', 'sync_batch_id')) {
                $table->uuid('sync_batch_id')->nullable()->after('sync_cursor')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shalom_recordar_records') || ! Schema::hasColumn('shalom_recordar_records', 'sync_batch_id')) {
            return;
        }

        Schema::table('shalom_recordar_records', function (Blueprint $table): void {
            $table->dropColumn('sync_batch_id');
        });
    }
};
