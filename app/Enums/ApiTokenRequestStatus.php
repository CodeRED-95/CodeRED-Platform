<?php

namespace App\Enums;

enum ApiTokenRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente', self::Approved => 'Aprobada', self::Rejected => 'Rechazada', self::Expired => 'Vencida', self::Cancelled => 'Cancelada',
        };
    }
}
