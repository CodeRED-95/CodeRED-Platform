<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente', self::Connected => 'Conectado', self::Disconnected => 'Desconectado', self::Disabled => 'Deshabilitado',
        };
    }
}
