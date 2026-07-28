<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table): void {
            $table->uuid('instance_uuid')->nullable()->after('integration_uuid');
            $table->unique(['created_by', 'provider', 'instance_uuid'], 'integrations_owner_provider_instance_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table): void {
            $table->dropUnique('integrations_owner_provider_instance_uuid_unique');
            $table->dropColumn('instance_uuid');
        });
    }
};
