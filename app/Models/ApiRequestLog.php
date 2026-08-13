<?php

namespace App\Models;

use App\Core\Api\Enums\ApiRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['api_client_id', 'token_id', 'delegated_user_id', 'request_id', 'is_duplicate_request', 'service', 'endpoint', 'method', 'status_code', 'ip_address', 'user_agent', 'identifier_hash', 'response_time_ms', 'request_type', 'source', 'provider_called', 'provider_status_code', 'cache_hit', 'local_database_hit', 'created_at'];

    protected function casts(): array
    {
        return ['request_type' => ApiRequestType::class, 'created_at' => 'datetime', 'provider_called' => 'boolean', 'cache_hit' => 'boolean', 'local_database_hit' => 'boolean', 'is_duplicate_request' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'token_id');
    }

    /** Usuario final auditado (distinto del api_client_id, que identifica la app llamante). */
    public function delegatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_user_id')->withTrashed();
    }
}
