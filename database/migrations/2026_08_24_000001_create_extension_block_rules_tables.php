<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extension_block_rules')) {
            Schema::create('extension_block_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('label', 120);
                $table->string('host_pattern', 190);
                $table->string('path_pattern', 190)->default('/*');
                // 'allowed': las ventanas son el horario permitido y fuera de ellas se bloquea.
                // 'blocked': las ventanas son el horario bloqueado y fuera de ellas se permite.
                $table->string('window_mode', 16)->default('allowed');
                $table->string('timezone', 64)->default('America/Lima');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampsTz();

                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('extension_block_windows')) {
            Schema::create('extension_block_windows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('extension_block_rule_id')->constrained('extension_block_rules')->cascadeOnDelete();
                // 0 = domingo ... 6 = sabado (mismo criterio que Date::getDay() en JavaScript).
                $table->unsignedTinyInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->timestampsTz();

                $table->index(['extension_block_rule_id', 'day_of_week']);
            });
        }

        $this->seedDefaultRule();
    }

    /**
     * Reproduce el bloqueo que hasta ahora estaba escrito a fuego en la
     * extension (sysnewos.shalomcontrol.com/service-order, 08:00-20:05 hora de
     * Lima). Sin esta fila, la primera version que lea las reglas del panel se
     * encontraria un conjunto vacio y dejaria de bloquear.
     */
    private function seedDefaultRule(): void
    {
        if (DB::table('extension_block_rules')->exists()) {
            return;
        }

        $now = now();

        $ruleId = DB::table('extension_block_rules')->insertGetId([
            'label' => 'Service Order',
            'host_pattern' => 'sysnewos.shalomcontrol.com',
            'path_pattern' => '/service-order',
            'window_mode' => 'allowed',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'sort_order' => 0,
            'notes' => 'Horario heredado de la version 2.3.15 de la extension.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('extension_block_windows')->insert(
            collect(range(0, 6))
                ->map(fn (int $day): array => [
                    'extension_block_rule_id' => $ruleId,
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '20:05:00',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_block_windows');
        Schema::dropIfExists('extension_block_rules');
    }
};
