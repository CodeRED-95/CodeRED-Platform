<?php

namespace App\Modules\Agencies\Support;

use App\Modules\Agencies\Enums\AgencyStatus;

final class AgencyStatusLabels
{
    public static function label(AgencyStatus|string $status): string
    {
        $enum = $status instanceof AgencyStatus ? $status : AgencyStatus::from($status);

        return $enum->label();
    }
}
