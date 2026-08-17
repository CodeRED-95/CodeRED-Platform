<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * El refresh token presentado no sirve: no existe, caducó, ya se canjeó o su
 * sesión fue revocada. El cliente debe volver a pedir credenciales.
 *
 * El mensaje es deliberadamente genérico hacia fuera: no distingue entre "no
 * existe" y "caducado" para no dar pistas a quien pruebe tokens al azar.
 */
class InvalidRefreshTokenException extends RuntimeException {}
