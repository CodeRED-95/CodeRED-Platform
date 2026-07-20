<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['api_client_id', 'token_id', 'service', 'endpoint', 'method', 'status_code', 'ip_address', 'user_agent', 'identifier_hash', 'response_time_ms', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'token_id');
    }
}
