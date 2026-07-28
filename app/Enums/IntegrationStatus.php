<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Degraded = 'degraded';
    case Unauthorized = 'unauthorized';
    case Disabled = 'disabled';
    case Revoked = 'revoked';
    case SecretRotationPending = 'secret_rotation_pending';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Emparejamiento incompleto', self::Connected => 'Conectado', self::Disconnected => 'Desconectado', self::Degraded => 'Degradado', self::Unauthorized => 'No autorizado', self::Disabled => 'Deshabilitado', self::Revoked => 'Revocado', self::SecretRotationPending => 'Rotación de secreto pendiente',
        };
    }
}
