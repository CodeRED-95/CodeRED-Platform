<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varios dominios por regla.
 *
 * `extension_block_rules.host_pattern` se conserva y sigue apuntando al primer
 * dominio: la extension 2.4.0 ya publicada lo lee como cadena y descarta
 * cualquier regla que no lo traiga. Quitarlo dejaria sin bloqueo a todas esas
 * instalaciones hasta que se actualicen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extension_block_rule_hosts')) {
            Schema::create('extension_block_rule_hosts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('extension_block_rule_id')->constrained('extension_block_rules')->cascadeOnDelete();
                $table->string('host_pattern', 190);
                $table->timestampsTz();

                $table->unique(['extension_block_rule_id', 'host_pattern']);
            });
        }

        $this->backfillExistingHosts();
    }

    /**
     * Cada regla ya existente pasa a tener su dominio actual como primera
     * entrada de la nueva tabla.
     */
    private function backfillExistingHosts(): void
    {
        $now = now();

        $rows = DB::table('extension_block_rules')
            ->leftJoin('extension_block_rule_hosts', 'extension_block_rule_hosts.extension_block_rule_id', '=', 'extension_block_rules.id')
            ->whereNull('extension_block_rule_hosts.id')
            ->select('extension_block_rules.id', 'extension_block_rules.host_pattern')
            ->get();

        foreach ($rows as $row) {
            if (trim((string) $row->host_pattern) === '') {
                continue;
            }

            DB::table('extension_block_rule_hosts')->insertOrIgnore([
                'extension_block_rule_id' => $row->id,
                'host_pattern' => $row->host_pattern,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_block_rule_hosts');
    }
};
