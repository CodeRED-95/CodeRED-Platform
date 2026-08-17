<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refresh token de una sesión de cliente.
 *
 * Del valor en claro sólo existe copia en el dispositivo: aquí se guarda un
 * SHA-256. La búsqueda es por hash, de modo que un volcado de esta tabla no
 * permite renovar ninguna sesión.
 *
 * Se rota en cada uso: al canjearlo se marca `used_at` y se apunta al sucesor en
 * `replaced_by_id`. Si vuelve a llegar uno ya usado, es reutilización —el token
 * quedó en manos de un tercero— y se revoca la sesión completa.
 */
class ClientRefreshToken extends Model
{
    protected $fillable = [
        'client_session_id',
        'token_hash',
        'expires_at',
        'used_at',
        'replaced_by_id',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** Hash con el que se almacena y se busca un refresh token. */
    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /** @return BelongsTo<ClientSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClientSession::class, 'client_session_id');
    }

    /** @return BelongsTo<self, $this> */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }

    /** @param Builder<self> $query */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
