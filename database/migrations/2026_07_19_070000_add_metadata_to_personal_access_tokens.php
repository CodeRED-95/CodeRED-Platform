<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
            $table->foreignId('created_by')->nullable()->after('tokenable_id')->constrained('users')->nullOnDelete();
            $table->index('expires_at');
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['last_used_at']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('description');
        });
    }
};
