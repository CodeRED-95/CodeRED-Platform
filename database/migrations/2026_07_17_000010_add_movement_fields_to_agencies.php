<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->boolean('is_operations_center')->default(false)->index()->after('size');
            $table->boolean('has_moved')->default(false)->index()->after('is_operations_center');
            $table->foreignId('moved_to_agency_id')
                ->nullable()
                ->constrained('agencies')
                ->nullOnDelete()
                ->after('has_moved');
            $table->text('moved_to_address')->nullable()->after('moved_to_agency_id');
            $table->text('move_notice')->nullable()->after('moved_to_address');
            $table->date('moved_at')->nullable()->after('move_notice');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('moved_to_agency_id');
            $table->dropColumn([
                'is_operations_center',
                'has_moved',
                'moved_to_address',
                'move_notice',
                'moved_at',
            ]);
        });
    }
};
