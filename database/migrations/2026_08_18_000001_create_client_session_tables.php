<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones de los clientes oficiales (Platform, Mobile, Desktop).
 *
 * Separa dos cosas que hasta ahora vivían mezcladas en personal_access_tokens:
 * la sesión de una persona y el token de una integración. El token de acceso
 * sigue siendo un PAT de Sanctum —para no reescribir la autenticación— pero
 * queda marcado con `kind = session`, caduca en minutos y se renueva con un
 * refresh token del que sólo se guarda el hash.
 *
 * Los tokens existentes no se tocan: la columna `kind` nace con el valor
 * `integration`, que es exactamente su comportamiento actual.
 */
return new class extends Migration
{
    /**
     * Aplicaciones cliente reconocidas. Se declara aquí y en ClientApplication;
     * la restricción vive en base para que ninguna escritura directa la esquive.
     *
     * @var list<string>
     */
    private const APPLICATIONS = ['platform', 'mobile', 'desktop'];

    public function up(): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'kind')) {
            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->string('kind', 20)->default('integration')->index();
            });
        }

        if (! Schema::hasTable('client_sessions')) {
            Schema::create('client_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('application', 20);
                $table->string('device_name', 120)->nullable();
                $table->string('platform', 60)->nullable();
                $table->string('client_version', 40)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();

                // El access token vigente. Se pone a null al rotar o revocar, y
                // nullOnDelete evita que borrar el PAT deje la sesión colgando.
                $table->foreignId('access_token_id')
                    ->nullable()
                    ->constrained('personal_access_tokens')
                    ->nullOnDelete();

                $table->timestamp('last_used_at')->nullable()->index();
                $table->timestamp('revoked_at')->nullable()->index();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('revocation_reason', 80)->nullable();
                $table->timestamps();

                // Listar "mis sesiones activas" es la consulta más frecuente.
                $table->index(['user_id', 'revoked_at']);
            });

            DB::statement(
                'ALTER TABLE client_sessions ADD CONSTRAINT client_sessions_application_check '
                ."CHECK (application IN ('".implode("','", self::APPLICATIONS)."'))"
            );
        }

        if (! Schema::hasTable('client_refresh_tokens')) {
            Schema::create('client_refresh_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_session_id')->constrained()->cascadeOnDelete();

                // Sólo el hash. Un volcado de esta tabla no permite renovar nada.
                $table->string('token_hash', 64)->unique();

                $table->timestamp('expires_at')->index();

                // Marca de rotación: un refresh usado dos veces delata un robo y
                // obliga a revocar la cadena entera.
                $table->timestamp('used_at')->nullable();
                $table->foreignId('replaced_by_id')
                    ->nullable()
                    ->constrained('client_refresh_tokens')
                    ->nullOnDelete();
                $table->timestamps();

                $table->index(['client_session_id', 'used_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_refresh_tokens');
        Schema::dropIfExists('client_sessions');

        if (Schema::hasColumn('personal_access_tokens', 'kind')) {
            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->dropColumn('kind');
            });
        }
    }
};
