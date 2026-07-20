<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\PersonalAccessToken;

class ApiToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    protected $fillable = ['name', 'description', 'token', 'abilities', 'expires_at', 'revoked_at', 'created_by'];

    protected function casts(): array
    {
        return ['abilities' => 'json', 'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class, 'token_id');
    }
}
