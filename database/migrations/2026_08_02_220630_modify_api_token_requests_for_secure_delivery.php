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
        Schema::table('api_token_requests', function (Blueprint $table) {
            // Columnas para el nuevo flujo de solicitud y consulta pública
            $table->string('tracking_code', 12)->unique()->nullable()->after('request_uuid');
            $table->text('requester_name_encrypted')->nullable()->after('requester_name');
            $table->string('requester_email_blind_index')->nullable()->index()->after('requester_email');
            $table->text('requester_phone_encrypted')->nullable()->after('requester_phone');
            $table->text('purpose_encrypted')->nullable()->after('purpose');

            // Columnas para el almacenamiento seguro del token
            $table->renameColumn('encrypted_plain_text_token', 'token_ciphertext');
            $table->string('token_hash', 64)->nullable()->after('encrypted_plain_text_token');
            $table->string('token_last_four', 4)->nullable()->after('token_hash');

            // Columnas para la revelación segura del token
            $table->timestamp('token_revealed_at')->nullable()->after('personal_access_token_id');
            $table->string('token_revealed_by_type')->nullable()->after('token_revealed_at'); // 'admin', 'public_requester'
            $table->foreignId('token_revealed_by_user_id')->nullable()->after('token_revealed_by_type')->constrained('users')->nullOnDelete();

            // Columnas para la entrega manual y auditoría
            $table->text('delivery_method_encrypted')->nullable()->after('delivered_by');
            $table->text('delivery_reason_encrypted')->nullable()->after('delivery_method_encrypted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_code',
                'requester_name_encrypted',
                'requester_email_blind_index',
                'requester_phone_encrypted',
                'purpose_encrypted',
                'token_hash',
                'token_last_four',
                'token_revealed_at',
                'token_revealed_by_type',
                'token_revealed_by_user_id',
                'delivery_method_encrypted',
                'delivery_reason_encrypted',
            ]);

            $table->renameColumn('token_ciphertext', 'encrypted_plain_text_token');
        });
    }
};
