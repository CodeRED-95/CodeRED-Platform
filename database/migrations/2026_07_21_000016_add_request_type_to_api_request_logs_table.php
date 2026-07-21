<?php

use App\Core\Api\Enums\ApiRequestType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'api_request_logs_request_type_service_index';

    public function up(): void
    {
        if (! Schema::hasColumn('api_request_logs', 'request_type')) {
            Schema::table('api_request_logs', function (Blueprint $table): void {
                $table->string('request_type', 30)->default(ApiRequestType::Api->value);
            });
        }

        DB::table('api_request_logs')->whereNull('request_type')->update([
            'request_type' => ApiRequestType::Api->value,
        ]);

        if (! $this->indexExists()) {
            Schema::table('api_request_logs', function (Blueprint $table): void {
                $table->index(['request_type', 'service'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('api_request_logs', 'request_type')) {
            return;
        }

        if ($this->indexExists()) {
            Schema::table('api_request_logs', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }

        Schema::table('api_request_logs', function (Blueprint $table): void {
            $table->dropColumn('request_type');
        });
    }

    private function indexExists(): bool
    {
        return collect(Schema::getIndexes('api_request_logs'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === self::INDEX);
    }
};
