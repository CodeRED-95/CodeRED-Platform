<?php

namespace App\Modules\Agencies\Enums;

enum AgencyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case TemporarilyClosed = 'temporarily_closed';
    case UnderReview = 'under_review';
    case Moved = 'moved';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Inactive => 'Inactiva',
            self::TemporarilyClosed => 'Cerrada temporalmente',
            self::UnderReview => 'En revisión',
            self::Moved => 'Trasladada',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
