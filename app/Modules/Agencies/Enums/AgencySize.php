<?php

namespace App\Modules\Agencies\Enums;

enum AgencySize: string
{
    case Large = 'large';
    case Medium = 'medium';
    case Small = 'small';

    public function label(): string
    {
        return match ($this) {
            self::Large => 'Grande',
            self::Medium => 'Mediano',
            self::Small => 'Pequeño',
        };
    }
}
