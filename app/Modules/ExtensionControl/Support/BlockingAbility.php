<?php

declare(strict_types=1);

namespace App\Modules\ExtensionControl\Support;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Ability que habilita el control horario en una instalacion concreta.
 *
 * No la lleva ningun tipo de token por defecto: se concede token a token desde
 * el panel de tokens. Una instalacion sin ella no recibe reglas, no muestra la
 * tarjeta de control en el popup y no bloquea nada.
 */
final class BlockingAbility
{
    public const NAME = 'extension:blocking';

    public static function grantedTo(PersonalAccessToken $token): bool
    {
        $abilities = array_filter((array) ($token->abilities ?? []), 'is_string');

        return in_array(self::NAME, $abilities, true) || in_array('*', $abilities, true);
    }

    /**
     * @return list<string>
     */
    public static function toggle(PersonalAccessToken $token): array
    {
        $abilities = array_values(array_unique(array_filter((array) ($token->abilities ?? []), 'is_string')));

        if (self::grantedTo($token)) {
            return array_values(array_filter($abilities, static fn (string $ability): bool => $ability !== self::NAME));
        }

        return array_values(array_unique([...$abilities, self::NAME]));
    }
}
