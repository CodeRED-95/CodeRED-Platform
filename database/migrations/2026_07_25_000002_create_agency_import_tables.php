<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agency_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('status');
            $table->integer('progress')->default(0);
            $table->string('stage')->nullable();
            $table->string('chosen_original_name')->nullable();
            $table->string('chosen_storage_path')->nullable();
            $table->string('extracted_json_path')->nullable();
            $table->string('report_json_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total_received')->default(0);
            $table->unsignedInteger('total_processed')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('renamed_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('agency_import_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->foreignId('matched_agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->string('action');
            $table->unsignedInteger('confidence')->default(0);
            $table->jsonb('incoming_data');
            $table->jsonb('current_data')->nullable();
            $table->jsonb('differences')->nullable();
            $table->string('proposed_old_name')->nullable();
            $table->string('conflict_reason')->nullable();
            $table->boolean('selected')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_import_items');
        Schema::dropIfExists('agency_import_runs');
    }
};
