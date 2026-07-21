<?php

namespace App\Providers;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Models\ApiToken;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\UserPolicy;
use App\Services\Dni\PeruDevsDniProvider;
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
    public function register(): void
    {
        $this->app->bind(DniProviderInterface::class, PeruDevsDniProvider::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);
        User::observe(UserObserver::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super-admin') || $user->hasPermission($ability) ? true : null;
        });
        RateLimiter::for('api', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('api.rate_limit_per_minute'), 1), 'api'));
        RateLimiter::for('api-agencias', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('api.agency_rate_limit_per_minute'), 1), 'agencias'));
        RateLimiter::for('api-dni', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('dni.rate_limit_per_minute'), 1), 'dni'));
        RateLimiter::for('ruc-lookup', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('ruc.rate_limit_per_minute'), 1), 'ruc'));
        RateLimiter::for('ruc-search', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('ruc.search_rate_limit_per_minute'), 1), 'ruc-search'));
        RateLimiter::for('ruc-admin-test', fn (Request $request): Limit => Limit::perMinute(20)->by('ruc-admin:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    private function tokenLimit(Request $request, int $limit, string $service): Limit
    {
        $owner = $request->user();
        $token = $owner?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $prefix = $service === 'api' ? '' : $service.':';

            return Limit::perMinute($limit)->by($prefix.'token:'.$token->getKey());
        }
        if ($token instanceof TransientToken && $owner !== null) {
            $prefix = $service === 'api' ? '' : $service.':';

            return Limit::perMinute($limit)->by($prefix.'user:'.$owner->getAuthIdentifier());
        }
        if ($owner !== null) {
            $prefix = $service === 'api' ? '' : $service.':';

            return Limit::perMinute($limit)->by($prefix.'user:'.$owner->getAuthIdentifier());
        }
        $prefix = $service === 'api' ? '' : $service.':';

        return Limit::perMinute($limit)->by($prefix.'ip:'.$request->ip());
    }
}
