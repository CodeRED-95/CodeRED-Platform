<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruc_imports', function (Blueprint $table): void {
            $table->string('queue_name', 100)->default('ruc-imports');
            $table->uuid('job_uuid')->nullable()->index();
            $table->text('last_message')->nullable();
            $table->timestamp('cancel_requested_at')->nullable()->index();
        });

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::table('ruc_imports', function (Blueprint $table): void {
            $table->dropIndex(['job_uuid']);
            $table->dropIndex(['cancel_requested_at']);
            $table->dropColumn(['queue_name', 'job_uuid', 'last_message', 'cancel_requested_at']);
        });
    }
};
