<?php

namespace App\Enums;

enum ApiTokenRequestType: string
{
    case Issuance = 'issuance';
    case Rotation = 'rotation';

    public function label(): string
    {
        return match ($this) {
            self::Issuance => 'Generación',
            self::Rotation => 'Rotación',
        };
    }
}
