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
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\TransientToken;

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
            $limit = max((int) config('api.rate_limit_per_minute'), 1);
            $user = $request->user();
            $token = $user?->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                return Limit::perMinute($limit)->by('token:'.$token->getKey());
            }

            if ($token instanceof TransientToken && $user !== null) {
                return Limit::perMinute($limit)->by('user:'.$user->getAuthIdentifier());
            }

            if ($user !== null) {
                return Limit::perMinute($limit)->by('user:'.$user->getAuthIdentifier());
            }

            return Limit::perMinute($limit)->by('ip:'.$request->ip());
        });
    }
}
