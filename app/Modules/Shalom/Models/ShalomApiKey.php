<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShalomApiKey extends Model
{
    protected $table = 'shalom_api_keys';

    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'description',
        'user_id',
    ];

    protected $hidden = ['key_hash'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Genera una nueva API Key y retorna la clave en plaintext (solo una vez)
     */
    public static function createNewKey(string $name, ?User $user = null, ?string $description = null): array
    {
        $plainKey = 'shalom_'.Str::random(40);
        $keyHash = hash('sha256', $plainKey);
        $keyPrefix = substr($plainKey, 0, 20);

        $apiKey = static::create([
            'name' => $name,
            'key_hash' => $keyHash,
            'key_prefix' => $keyPrefix,
            'description' => $description,
            'user_id' => $user?->id,
        ]);

        return [
            'id' => $apiKey->id,
            'plain_key' => $plainKey,  // Solo retornarlo una vez (no guardarlo)
            'key_prefix' => $keyPrefix,
            'created_at' => $apiKey->created_at,
        ];
    }

    /**
     * Verifica si una clave plaintext corresponde a esta API Key
     */
    public function verifyKey(string $plainKey): bool
    {
        if ($this->revoked_at) {
            return false;
        }

        return hash('sha256', $plainKey) === $this->key_hash;
    }

    /**
     * Registra el uso de esta clave
     */
    public function recordUsage(): void
    {
        $this->update([
            'requests_count' => $this->requests_count + 1,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Revoca esta clave (la invalida sin eliminarla)
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    /**
     * Scope: solo claves activas
     */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Scope: solo claves no usadas
     */
    public function scopeUnused($query)
    {
        return $query->whereNull('last_used_at');
    }
}
