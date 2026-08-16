<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MobileDeviceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un dispositivo móvil que puede recibir notificaciones push.
 *
 * El token de entrega se guarda cifrado y nunca sale de aquí: no aparece en
 * recursos de API, ni en logs, ni en la auditoría. Lo único que se publica del
 * dispositivo es su identificador interno, que no sirve para enviar nada.
 */
class MobileDevice extends Model
{
    /** @use HasFactory<MobileDeviceFactory> */
    use HasFactory;

    public const PLATFORM_ANDROID = 'android';

    protected $fillable = [
        'user_id',
        'platform',
        'push_token',
        'push_token_hash',
        'device_name',
        'app_version',
        'last_seen_at',
    ];

    /**
     * Oculto también en serialización accidental: si alguien hiciera
     * `->toArray()` sobre el modelo, el token no debe salir.
     *
     * @var list<string>
     */
    protected $hidden = ['push_token', 'push_token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'push_token' => 'encrypted',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Identidad estable del token sin guardarlo en claro.
     *
     * Es lo que permite el índice único y el upsert: el mismo token siempre da
     * el mismo hash, y sin él no se puede reconstruir el token.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): Factory
    {
        return MobileDeviceFactory::new();
    }
}
