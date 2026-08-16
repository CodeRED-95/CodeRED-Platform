<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Una solicitud de token no admite la transición pedida: ya fue procesada, ya
 * venció, o el destinatario no está activo.
 *
 * Es una excepción propia y no un abort() porque la lanzan acciones que usan
 * dos frontales distintos —el panel Livewire y la API móvil—, y cada uno la
 * traduce a su forma: el panel a un 422 de Laravel, la API a una respuesta JSON
 * que AuditApiRequest todavía puede registrar.
 */
class TokenRequestTransitionException extends RuntimeException
{
    public static function alreadyProcessed(): self
    {
        return new self('La solicitud ya fue procesada.');
    }

    public static function expired(): self
    {
        return new self('La solicitud ya venció.');
    }
}
