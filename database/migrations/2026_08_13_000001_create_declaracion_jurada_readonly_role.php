<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rol PostgreSQL de solo lectura para el bridge de autenticación de
 * Declaración Jurada Shalom (packages/shalom-declaracion-jurada). Sustituye
 * el uso de las credenciales completas de la app (DB_USERNAME/DB_PASSWORD)
 * por un rol con el mínimo privilegio necesario: SELECT únicamente sobre
 * users/roles/permissions/role_user/permission_role — sin poder modificar,
 * eliminar ni acceder a ninguna otra tabla.
 *
 * Requiere DECLARACION_JURADA_DB_PASSWORD en el entorno (ver
 * ensure_declaracion_jurada_env en update.sh, que la genera si falta).
 * Sin esa variable la migración no falla: simplemente no crea/actualiza el
 * rol, para no romper `php artisan migrate` en entornos donde Declaración
 * Jurada no está desplegada.
 */
return new class extends Migration
{
    private const ROLE = 'declaracion_jurada_ro';

    public function up(): void
    {
        $password = (string) config('services.declaracion_jurada.db_password', '');

        if ($password === '') {
            return;
        }

        $database = (string) config('database.connections.pgsql.database');
        $escapedPassword = str_replace("'", "''", $password);

        DB::statement(<<<SQL
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '{$this->role()}') THEN
                    CREATE ROLE {$this->role()} LOGIN PASSWORD '{$escapedPassword}';
                ELSE
                    ALTER ROLE {$this->role()} WITH LOGIN PASSWORD '{$escapedPassword}';
                END IF;
            END
            \$\$;
        SQL);

        DB::statement("GRANT CONNECT ON DATABASE \"{$database}\" TO {$this->role()}");
        DB::statement("GRANT USAGE ON SCHEMA public TO {$this->role()}");
        DB::statement("GRANT SELECT ON users, roles, permissions, role_user, permission_role TO {$this->role()}");
    }

    public function down(): void
    {
        DB::statement("REVOKE ALL PRIVILEGES ON users, roles, permissions, role_user, permission_role FROM {$this->role()}");
        DB::statement("DROP ROLE IF EXISTS {$this->role()}");
    }

    private function role(): string
    {
        return self::ROLE;
    }
};
