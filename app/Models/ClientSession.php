<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sesión de una persona en un cliente oficial de CodeRED.
 *
 * Es la unidad de revocación: cerrar una sesión invalida a la vez su access
 * token y todos sus refresh tokens, sin tocar las demás sesiones del usuario ni
 * ningún token de integración.
 */
class ClientSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'application',
        'device_name',
        'platform',
        'client_version',
        'ip_address',
        'user_agent',
        'access_token_id',
        'last_used_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'application' => ClientApplication::class,
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (! is_string($session->uuid) || ! Str::isUuid($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PersonalAccessToken, $this> */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'access_token_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** @return HasMany<ClientRefreshToken, $this> */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(ClientRefreshToken::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** @param Builder<self> $query */
    public function scopeForApplication(Builder $query, ClientApplication $application): Builder
    {
        return $query->where('application', $application->value);
    }
}
