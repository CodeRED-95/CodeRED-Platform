<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            if (! Schema::hasColumn('agencies', 'place')) {
                $table->text('place')->nullable();
            }
            if (! Schema::hasColumn('agencies', 'zone')) {
                $table->string('zone')->nullable();
            }
            if (! Schema::hasColumn('agencies', 'schedule_general')) {
                $table->text('schedule_general')->nullable();
            }
            if (! Schema::hasColumn('agencies', 'schedule_sunday')) {
                $table->text('schedule_sunday')->nullable();
            }
            if (! Schema::hasColumn('agencies', 'classification_category')) {
                $table->string('classification_category')->nullable();
            }
            if (! Schema::hasColumn('agencies', 'classification_sends_category')) {
                $table->string('classification_sends_category')->nullable();
            }
            if (! Schema::hasColumn('agencies', 'classification_receives_category')) {
                $table->string('classification_receives_category')->nullable();
            }
        });

        DB::statement("UPDATE agencies SET place = concat_ws(' / ', nullif(department, ''), nullif(province, ''), nullif(district, ''), nullif(name, '')) WHERE place IS NULL");
        DB::statement('UPDATE agencies SET schedule_general = schedule WHERE schedule_general IS NULL AND schedule IS NOT NULL');

        foreach ([
            'external_id' => 'agencies_external_id_idx',
            'code' => 'agencies_code_idx',
            'name' => 'agencies_name_idx',
            'classification_category' => 'agencies_classification_category_idx',
        ] as $column => $index) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$index} ON agencies ({$column})");
        }
    }

    public function down(): void
    {
        foreach ([
            'agencies_external_id_idx',
            'agencies_code_idx',
            'agencies_name_idx',
            'agencies_classification_category_idx',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        Schema::table('agencies', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('agencies', 'place') ? 'place' : null,
                Schema::hasColumn('agencies', 'zone') ? 'zone' : null,
                Schema::hasColumn('agencies', 'schedule_general') ? 'schedule_general' : null,
                Schema::hasColumn('agencies', 'schedule_sunday') ? 'schedule_sunday' : null,
                Schema::hasColumn('agencies', 'classification_category') ? 'classification_category' : null,
                Schema::hasColumn('agencies', 'classification_sends_category') ? 'classification_sends_category' : null,
                Schema::hasColumn('agencies', 'classification_receives_category') ? 'classification_receives_category' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
