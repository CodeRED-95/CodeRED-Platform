<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Models\Agency;

class AgencyMapUrlGenerator
{
    public function __invoke(?Agency $agency): ?string
    {
        if ($agency === null) {
            return null;
        }

        $latitude = $agency->latitude;
        $longitude = $agency->longitude;

        if (
            ! is_numeric($latitude) || ! is_numeric($longitude) ||
            $latitude < -90 || $latitude > 90 ||
            $longitude < -180 || $longitude > 180
        ) {
            return null;
        }

        return "https://www.google.com/maps/dir/?api=1&destination={$latitude},{$longitude}";
    }
}
