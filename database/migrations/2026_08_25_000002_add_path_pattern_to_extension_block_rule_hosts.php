<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruta propia por destino.
 *
 * Con una unica ruta por regla, anadir un segundo dominio que se sirve en otra
 * ruta obligaba a duplicar la regla entera. Ahora cada destino puede traer la
 * suya; en NULL hereda la de la regla, que es como se comportaban todas las
 * filas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extension_block_rule_hosts') && ! Schema::hasColumn('extension_block_rule_hosts', 'path_pattern')) {
            Schema::table('extension_block_rule_hosts', function (Blueprint $table): void {
                $table->string('path_pattern', 190)->nullable()->after('host_pattern');
                // El unico anterior era (regla, dominio): impedia que un mismo
                // dominio apareciese dos veces con rutas distintas, que es
                // justo lo que esta columna viene a permitir.
                $table->dropUnique('extension_block_rule_hosts_extension_block_rule_id_host_pattern');
                $table->unique(['extension_block_rule_id', 'host_pattern', 'path_pattern'], 'extension_block_rule_hosts_destination_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('extension_block_rule_hosts') && Schema::hasColumn('extension_block_rule_hosts', 'path_pattern')) {
            Schema::table('extension_block_rule_hosts', function (Blueprint $table): void {
                $table->dropColumn('path_pattern');
            });
        }
    }
};
