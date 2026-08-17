<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados de una solicitud de acceso a un módulo móvil.
 *
 * Mismo vocabulario que ApiTokenRequestStatus, con el que comparte forma pero
 * no significado: aquélla pide un token de integración, ésta pide un permiso
 * para una persona. Se mantienen separadas a propósito.
 */
enum PermissionRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Etiqueta para la interfaz, en castellano. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
            self::Cancelled => 'Cancelada',
        };
    }

    /** Una solicitud resuelta ya no admite decisiones. */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
