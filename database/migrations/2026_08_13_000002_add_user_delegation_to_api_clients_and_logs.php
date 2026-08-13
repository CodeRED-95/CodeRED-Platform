<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que un ApiClient autorizado (p. ej. Declaración Jurada Shalom)
 * identifique, dentro de una llamada autenticada con SU token técnico,
 * a qué usuario final de CodeRED Platform corresponde la acción — sin que
 * eso permita a cualquier cliente falsificar un user_id arbitrario.
 *
 * - api_clients.can_delegate_users: opt-in explícito por cliente (default
 *   false). Sin este flag, cualquier header de delegación se ignora/rechaza.
 * - api_clients.delegation_permission: qué permiso de CodeRED debe tener
 *   el usuario delegado para que la delegación sea válida (reutiliza el
 *   sistema de permisos existente, no crea uno paralelo).
 * - api_request_logs.delegated_user_id: el usuario final auditado, además
 *   del api_client_id/token_id ya existentes (que identifican la app).
 * - api_request_logs.request_id: id de correlación enviado por el cliente
 *   (p. ej. X-Request-Id) para detectar reintentos duplicados.
 * - api_request_logs.is_duplicate_request: true si ese request_id ya
 *   había sido auditado antes (ver App\Http\Middleware\AuditApiRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_clients', 'can_delegate_users')) {
                $table->boolean('can_delegate_users')->default(false)->after('active');
            }
            if (! Schema::hasColumn('api_clients', 'delegation_permission')) {
                $table->string('delegation_permission')->nullable()->after('can_delegate_users');
            }
        });

        Schema::table('api_request_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_request_logs', 'delegated_user_id')) {
                $table->foreignId('delegated_user_id')->nullable()->after('token_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('api_request_logs', 'request_id')) {
                $table->string('request_id', 64)->nullable()->after('delegated_user_id');
            }
            if (! Schema::hasColumn('api_request_logs', 'is_duplicate_request')) {
                $table->boolean('is_duplicate_request')->default(false)->after('request_id');
            }
        });

        Schema::table('api_request_logs', function (Blueprint $table): void {
            if (! $this->indexExists('api_request_logs', 'api_request_logs_request_id_index')) {
                $table->index('request_id');
            }
            if (! $this->indexExists('api_request_logs', 'api_request_logs_delegated_user_id_created_at_index')) {
                $table->index(['delegated_user_id', 'created_at']);
            }
        });

        // Único ApiClient hoy con un puente de identidad de usuario final:
        // Declaración Jurada Shalom. declaracion-jurada.view es, en ese
        // contexto, el permiso que habilita tanto el acceso a la app como
        // la consulta DNI — no se crea un permiso nuevo (ver
        // database/seeders/PermissionsSeeder.php).
        DB::table('api_clients')
            ->where('name', 'Declaración Jurada Shalom')
            ->update([
                'can_delegate_users' => true,
                'delegation_permission' => 'declaracion-jurada.view',
            ]);
    }

    public function down(): void
    {
        Schema::table('api_request_logs', function (Blueprint $table): void {
            if ($this->indexExists('api_request_logs', 'api_request_logs_delegated_user_id_created_at_index')) {
                $table->dropIndex(['delegated_user_id', 'created_at']);
            }
            if ($this->indexExists('api_request_logs', 'api_request_logs_request_id_index')) {
                $table->dropIndex(['request_id']);
            }
        });

        Schema::table('api_request_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('api_request_logs', 'is_duplicate_request')) {
                $table->dropColumn('is_duplicate_request');
            }
            if (Schema::hasColumn('api_request_logs', 'request_id')) {
                $table->dropColumn('request_id');
            }
            if (Schema::hasColumn('api_request_logs', 'delegated_user_id')) {
                $table->dropConstrainedForeignId('delegated_user_id');
            }
        });

        Schema::table('api_clients', function (Blueprint $table): void {
            if (Schema::hasColumn('api_clients', 'delegation_permission')) {
                $table->dropColumn('delegation_permission');
            }
            if (Schema::hasColumn('api_clients', 'can_delegate_users')) {
                $table->dropColumn('can_delegate_users');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
