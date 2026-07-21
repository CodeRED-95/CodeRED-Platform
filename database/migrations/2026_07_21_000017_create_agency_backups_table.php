<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('filename');
            $table->string('disk', 50)->default('local');
            $table->string('path', 500)->unique();
            $table->unsignedInteger('record_count')->default(0);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('status', 20)->index();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_backups');
    }
};
