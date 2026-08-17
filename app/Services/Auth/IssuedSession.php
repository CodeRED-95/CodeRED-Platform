<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\ClientSession;
use Illuminate\Support\Carbon;

/**
 * Credenciales recién emitidas para una sesión de cliente.
 *
 * Los valores en claro sólo existen dentro de este objeto y del cuerpo de la
 * respuesta HTTP: en base de datos viven el hash del refresh y el hash que ya
 * gestiona Sanctum para el access token.
 */
final class IssuedSession
{
    public function __construct(
        public readonly ClientSession $session,
        public readonly string $accessToken,
        public readonly Carbon $accessTokenExpiresAt,
        public readonly string $refreshToken,
        public readonly Carbon $refreshTokenExpiresAt,
    ) {}

    /**
     * Cuerpo estándar de las respuestas de login y refresh.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->accessTokenExpiresAt->toIso8601String(),
            'expires_in' => max(0, now()->diffInSeconds($this->accessTokenExpiresAt, false)),
            'refresh_token' => $this->refreshToken,
            'refresh_token_expires_at' => $this->refreshTokenExpiresAt->toIso8601String(),
            'session' => [
                'uuid' => $this->session->uuid,
                'application' => $this->session->application->value,
                'device_name' => $this->session->device_name,
            ],
        ];
    }
}
