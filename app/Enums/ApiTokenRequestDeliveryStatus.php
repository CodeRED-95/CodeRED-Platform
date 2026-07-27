<?php

namespace App\Enums;

enum ApiTokenRequestDeliveryStatus: string
{
    case NotAvailable = 'not_available';
    case Pending = 'pending';
    case Retrieved = 'retrieved';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotAvailable => 'No disponible', self::Pending => 'Esperando entrega', self::Retrieved => 'Recuperado', self::Delivered => 'Entregada', self::Failed => 'Error de entrega',
        };
    }
}
