<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ubigeos', function (Blueprint $table): void {
            $table->string('capital', 120)->nullable();
            $table->string('source', 30)->default('alanube')->index();
            $table->string('source_url', 500)->default('https://developer.alanube.co/v1.0-PER/docs/ubigeo-table');
            $table->timestamp('source_updated_at')->nullable();
        });
        DB::table('ubigeos')->update([
            'source' => 'alanube',
            'source_url' => 'https://developer.alanube.co/v1.0-PER/docs/ubigeo-table',
        ]);
    }

    public function down(): void
    {
        Schema::table('ubigeos', function (Blueprint $table): void {
            $table->dropIndex(['source']);
            $table->dropColumn(['capital', 'source', 'source_url', 'source_updated_at']);
        });
    }
};
