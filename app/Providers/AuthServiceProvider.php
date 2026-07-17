<?php

namespace App\Providers;

use App\Modules\Agencies\Models\Agency;
use App\Policies\AgencyPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Agency::class => AgencyPolicy::class,
    ];
}
