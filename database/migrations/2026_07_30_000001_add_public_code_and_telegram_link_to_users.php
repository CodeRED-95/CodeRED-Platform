<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_code')->nullable()->unique()->after('id');
            $table->string('telegram_user_id', 80)->nullable()->index()->after('updated_by');
            $table->string('telegram_chat_id', 80)->nullable()->after('telegram_user_id');
            $table->string('telegram_username', 120)->nullable()->after('telegram_chat_id');
            $table->uuid('telegram_linked_integration_uuid')->nullable()->index()->after('telegram_username');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_linked_integration_uuid');
        });

        DB::table('users')
            ->whereNull('public_code')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['public_code' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['public_code']);
            $table->dropIndex(['telegram_user_id']);
            $table->dropIndex(['telegram_linked_integration_uuid']);
            $table->dropColumn([
                'public_code',
                'telegram_user_id',
                'telegram_chat_id',
                'telegram_username',
                'telegram_linked_integration_uuid',
                'telegram_linked_at',
            ]);
        });
    }
};
