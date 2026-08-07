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
        // This migration corrects the previous defective migration
        // that renamed encrypted_plain_text_token to token_ciphertext
        // but left the application code expecting encrypted_plain_text_token

        Schema::table('api_token_requests', function (Blueprint $table) {
            // Check if token_ciphertext exists (from previous broken migration)
            if (Schema::hasColumn('api_token_requests', 'token_ciphertext')) {
                // Rename back to the expected name
                $table->renameColumn('token_ciphertext', 'encrypted_plain_text_token');
            }

            // Remove token_hash if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'token_hash')) {
                $table->dropColumn('token_hash');
            }

            // Remove token_last_four if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'token_last_four')) {
                $table->dropColumn('token_last_four');
            }

            // Remove token_revealed_at if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'token_revealed_at')) {
                $table->dropColumn('token_revealed_at');
            }

            // Remove token_revealed_by_type if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'token_revealed_by_type')) {
                $table->dropColumn('token_revealed_by_type');
            }

            // Remove token_revealed_by_user_id if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'token_revealed_by_user_id')) {
                $table->dropForeign(['token_revealed_by_user_id']);
                $table->dropColumn('token_revealed_by_user_id');
            }

            // Remove delivery_method_encrypted if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'delivery_method_encrypted')) {
                $table->dropColumn('delivery_method_encrypted');
            }

            // Remove delivery_reason_encrypted if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'delivery_reason_encrypted')) {
                $table->dropColumn('delivery_reason_encrypted');
            }

            // Remove tracking_code if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'tracking_code')) {
                $table->dropUnique(['tracking_code']);
                $table->dropColumn('tracking_code');
            }

            // Remove requester_name_encrypted if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'requester_name_encrypted')) {
                $table->dropColumn('requester_name_encrypted');
            }

            // Remove requester_email_blind_index if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'requester_email_blind_index')) {
                $table->dropIndex('api_token_requests_requester_email_blind_index_index');
                $table->dropColumn('requester_email_blind_index');
            }

            // Remove requester_phone_encrypted if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'requester_phone_encrypted')) {
                $table->dropColumn('requester_phone_encrypted');
            }

            // Remove purpose_encrypted if it exists (added by broken migration)
            if (Schema::hasColumn('api_token_requests', 'purpose_encrypted')) {
                $table->dropColumn('purpose_encrypted');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting this correction is not supported as it would require
        // restoring the defective state. Users should not rollback this migration.
    }
};
