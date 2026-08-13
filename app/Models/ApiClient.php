<?php

namespace App\Models;

use Database\Factories\ApiClientFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ApiClient extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'description', 'contact_name', 'contact_email', 'active', 'can_delegate_users', 'delegation_permission', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'can_delegate_users' => 'boolean'];
    }

    protected static function newFactory(): Factory
    {
        return ApiClientFactory::new();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Si true, este cliente puede identificar en sus llamadas (vía
     * ResolveDelegatedUser) a qué usuario final de CodeRED corresponde la
     * acción. Sin este flag, cualquier header de delegación se rechaza —
     * ver database/migrations/2026_08_13_000002_add_user_delegation_to_api_clients_and_logs.php.
     */
    public function canDelegateUsers(): bool
    {
        return (bool) $this->can_delegate_users;
    }
}
