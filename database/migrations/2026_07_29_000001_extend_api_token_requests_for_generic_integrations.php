<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            $table->string('requester_name')->nullable()->after('request_uuid');
            $table->string('requester_email')->nullable()->after('requester_name');
            $table->string('requester_phone')->nullable()->after('requester_email');
            $table->string('application_name')->nullable()->after('requester_phone');
            $table->text('purpose')->nullable()->after('application_name');
            $table->json('metadata')->nullable()->after('request_source');
            $table->timestamp('cancelled_at')->nullable()->after('rejected_at');
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            $table->string('delivery_channel')->nullable()->after('delivered_at');
            $table->string('delivered_to')->nullable()->after('delivery_channel');
            $table->json('delivery_metadata')->nullable()->after('delivered_to');
        });
    }

    public function down(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'requester_name',
                'requester_email',
                'requester_phone',
                'application_name',
                'purpose',
                'metadata',
                'cancelled_at',
                'cancellation_reason',
                'delivery_channel',
                'delivered_to',
                'delivery_metadata',
            ]);
        });
    }
};
