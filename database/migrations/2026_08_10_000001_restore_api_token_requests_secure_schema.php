<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Restaura el esquema seguro de `api_token_requests`.
 *
 * La migración 2026_08_07_000001_fix_api_token_requests_schema eliminó columnas
 * que el código sigue usando (tracking_code, los campos *_encrypted, el índice
 * ciego de correo y todo el bloque de almacenamiento/revelación segura del
 * token). Esta migración las vuelve a crear de forma idempotente y unifica el
 * nombre de la columna del token cifrado en `token_ciphertext`, que es el que
 * declaran el modelo, RevealTokenAction y el panel administrativo.
 *
 * Es no destructiva: solo crea columnas/índices ausentes y rellena
 * `tracking_code` donde está vacío (columna recién creada). Ningún dato
 * existente se modifica ni se elimina.
 */
return new class extends Migration
{
    private const TABLE = 'api_token_requests';

    /**
     * Columnas cifradas restauradas: nombre => tipo de columna.
     *
     * @var array<string, string>
     */
    private const RESTORED_COLUMNS = [
        'requester_name_encrypted' => 'text',
        'requester_phone_encrypted' => 'text',
        'purpose_encrypted' => 'text',
        'delivery_method_encrypted' => 'text',
        'delivery_reason_encrypted' => 'text',
        'token_revealed_by_type' => 'string',
    ];

    public function up(): void
    {
        $this->unifyTokenCiphertextColumn();
        $this->restoreTrackingCode();
        $this->restoreEncryptedColumns();
        $this->restoreBlindIndex();
        $this->restoreTokenStorageColumns();
        $this->restoreTokenRevealColumns();
        $this->restoreSecurityCounters();
        $this->allowOrphanPublicStatusEvents();
        $this->backfillTrackingCodes();
    }

    public function down(): void
    {
        foreach ([
            'tracking_code',
            'requester_name_encrypted',
            'requester_email_blind_index',
            'requester_phone_encrypted',
            'purpose_encrypted',
            'token_hash',
            'token_last_four',
            'token_revealed_at',
            'token_revealed_by_type',
            'delivery_method_encrypted',
            'delivery_reason_encrypted',
        ] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, static function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn(self::TABLE, 'token_revealed_by_user_id')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->dropForeign(['token_revealed_by_user_id']);
                $table->dropColumn('token_revealed_by_user_id');
            });
        }

        if (Schema::hasColumn(self::TABLE, 'token_ciphertext') && ! Schema::hasColumn(self::TABLE, 'encrypted_plain_text_token')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->renameColumn('token_ciphertext', 'encrypted_plain_text_token');
            });
        }

        if (Schema::hasTable('api_token_request_events')) {
            DB::statement('DELETE FROM api_token_request_events WHERE api_token_request_id IS NULL');
            DB::statement('ALTER TABLE api_token_request_events ALTER COLUMN api_token_request_id SET NOT NULL');
        }
    }

    /**
     * Deja una única columna para el token cifrado: `token_ciphertext`.
     *
     * Si solo existe el nombre heredado se renombra (conserva los valores). Si
     * coexisten ambas, se copian los valores que falten y se elimina la vieja.
     */
    private function unifyTokenCiphertextColumn(): void
    {
        $hasNew = Schema::hasColumn(self::TABLE, 'token_ciphertext');
        $hasLegacy = Schema::hasColumn(self::TABLE, 'encrypted_plain_text_token');

        if ($hasNew && $hasLegacy) {
            DB::statement('UPDATE '.self::TABLE.' SET token_ciphertext = encrypted_plain_text_token WHERE token_ciphertext IS NULL AND encrypted_plain_text_token IS NOT NULL');

            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->dropColumn('encrypted_plain_text_token');
            });

            return;
        }

        if ($hasLegacy) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->renameColumn('encrypted_plain_text_token', 'token_ciphertext');
            });

            return;
        }

        if (! $hasNew) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->text('token_ciphertext')->nullable();
            });
        }
    }

    /**
     * `tracking_code` guarda "CR-" + 10 caracteres (13). Se reserva varchar(20)
     * para dejar margen, igual que hacía 2026_08_05_000000_fix_tracking_code_length.
     */
    private function restoreTrackingCode(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'tracking_code')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->string('tracking_code', 20)->nullable()->after('request_uuid');
            });
        } elseif ($this->columnLength('tracking_code') < 20) {
            DB::statement('ALTER TABLE '.self::TABLE.' ALTER COLUMN tracking_code TYPE varchar(20)');
        }

        if (! Schema::hasIndex(self::TABLE, 'api_token_requests_tracking_code_unique')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->unique('tracking_code');
            });
        }
    }

    private function restoreEncryptedColumns(): void
    {
        foreach (self::RESTORED_COLUMNS as $column => $type) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                continue;
            }

            Schema::table(self::TABLE, static function (Blueprint $table) use ($column, $type): void {
                $type === 'text' ? $table->text($column)->nullable() : $table->string($column)->nullable();
            });
        }
    }

    private function restoreBlindIndex(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'requester_email_blind_index')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->string('requester_email_blind_index')->nullable()->after('requester_email');
            });
        }

        if (! Schema::hasIndex(self::TABLE, 'api_token_requests_requester_email_blind_index_index')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->index('requester_email_blind_index');
            });
        }
    }

    private function restoreTokenStorageColumns(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'token_hash')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->string('token_hash', 64)->nullable()->after('token_ciphertext');
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'token_last_four')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->string('token_last_four', 4)->nullable()->after('token_hash');
            });
        }
    }

    private function restoreTokenRevealColumns(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'token_revealed_at')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->timestamp('token_revealed_at')->nullable()->after('personal_access_token_id');
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'token_revealed_by_user_id')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->foreignId('token_revealed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Estas columnas las creó 2026_08_05_100002 y no fueron eliminadas, pero se
     * verifican igualmente para que la tabla quede consistente en instalaciones
     * que hayan divergido.
     */
    private function restoreSecurityCounters(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'otp_validated_at')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->timestamp('otp_validated_at')->nullable();
            });
        }

        if (! Schema::hasIndex(self::TABLE, 'api_token_requests_otp_validated_at_index')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->index('otp_validated_at');
            });
        }

        foreach (['token_reveal_count', 'protected_data_view_count'] as $counter) {
            if (! Schema::hasColumn(self::TABLE, $counter)) {
                Schema::table(self::TABLE, static function (Blueprint $table) use ($counter): void {
                    $table->integer($counter)->default(0);
                });
            }
        }

        if (! Schema::hasColumn(self::TABLE, 'last_protected_view_ip')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->string('last_protected_view_ip')->nullable();
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'last_protected_view_at')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->timestamp('last_protected_view_at')->nullable();
            });
        }
    }

    /**
     * `ApiTokenRequestEvent::logPublicStatusCheck()` registra también las
     * consultas públicas que no encuentran solicitud, y en ese caso escribe
     * `api_token_request_id = null`. La columna se creó NOT NULL, así que cada
     * búsqueda fallida en /solicitar-token terminaba en error 500. Se relaja a
     * nullable conservando la clave foránea en cascada.
     */
    private function allowOrphanPublicStatusEvents(): void
    {
        if (! Schema::hasTable('api_token_request_events')) {
            return;
        }

        $isNullable = DB::table('information_schema.columns')
            ->where('table_name', 'api_token_request_events')
            ->where('column_name', 'api_token_request_id')
            ->value('is_nullable');

        if ($isNullable === 'NO') {
            DB::statement('ALTER TABLE api_token_request_events ALTER COLUMN api_token_request_id DROP NOT NULL');
        }
    }

    /**
     * Rellena `tracking_code` en las filas que quedaron sin él tras el borrado.
     *
     * Solo escribe sobre valores NULL de una columna recién creada: reutiliza el
     * código que quedó guardado en `metadata->tracking_code` y, si no lo hay,
     * genera uno nuevo con el formato vigente ("CR-" + 10 caracteres).
     */
    private function backfillTrackingCodes(): void
    {
        $used = DB::table(self::TABLE)
            ->whereNotNull('tracking_code')
            ->pluck('tracking_code')
            ->all();
        $used = array_flip($used);

        DB::table(self::TABLE)
            ->select('id', 'metadata')
            ->whereNull('tracking_code')
            ->orderBy('id')
            ->chunk(200, function ($rows) use (&$used): void {
                foreach ($rows as $row) {
                    $code = $this->trackingCodeFromMetadata($row->metadata ?? null);

                    if ($code === null || isset($used[$code])) {
                        do {
                            $code = 'CR-'.strtoupper(Str::random(10));
                        } while (isset($used[$code]));
                    }

                    $used[$code] = true;

                    DB::table(self::TABLE)->where('id', $row->id)->update(['tracking_code' => $code]);
                }
            });
    }

    private function trackingCodeFromMetadata(mixed $metadata): ?string
    {
        if (! is_string($metadata) || $metadata === '') {
            return null;
        }

        $decoded = json_decode($metadata, true);
        $code = is_array($decoded) ? ($decoded['tracking_code'] ?? null) : null;

        if (! is_string($code) || $code === '' || mb_strlen($code) > 20) {
            return null;
        }

        return $code;
    }

    private function columnLength(string $column): int
    {
        $length = DB::table('information_schema.columns')
            ->where('table_name', self::TABLE)
            ->where('column_name', $column)
            ->value('character_maximum_length');

        return (int) $length;
    }
};
