<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevokedApiToken extends Model
{
    public $timestamps = false;

    protected $fillable = ['original_token_id', 'name', 'owner_name', 'abilities', 'created_at', 'last_used_at', 'expires_at', 'revoked_at', 'revoked_by'];

    protected function casts(): array
    {
        return ['abilities' => 'array', 'created_at' => 'datetime', 'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
