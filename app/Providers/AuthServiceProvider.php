<?php

namespace App\Providers;

use App\Models\ApiTokenRequest;
use App\Modules\Agencies\Models\Agency;
use App\Policies\AgencyPolicy;
use App\Policies\ApiTokenRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Agency::class => AgencyPolicy::class,
        ApiTokenRequest::class => ApiTokenRequestPolicy::class,
    ];
}
