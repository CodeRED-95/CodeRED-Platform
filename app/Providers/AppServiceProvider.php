<?php

namespace App\Providers;

use App\Models\ApiToken;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);
        User::observe(UserObserver::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            if ($user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $tokenId = $request->user()?->currentAccessToken()?->getKey();

            return Limit::perMinute(max((int) config('api.rate_limit_per_minute'), 1))
                ->by($tokenId !== null ? 'token:'.$tokenId : 'ip:'.$request->ip());
        });
    }
}
