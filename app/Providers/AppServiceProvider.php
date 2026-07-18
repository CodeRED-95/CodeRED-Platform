<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use App\Modules\Agencies\Models\Agency;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            $mappedPermission = $this->mapAbilityToPermission($ability);

            if ($mappedPermission !== null && $user->hasPermission($mappedPermission)) {
                return true;
            }

            if ($user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function mapAbilityToPermission(string $ability): ?string
    {
        return match ($ability) {
            'viewAny', 'view' => 'agencies.view',
            'create' => 'agencies.create',
            'update' => 'agencies.update',
            'delete' => 'agencies.delete',
            'restore' => 'agencies.restore',
            'import' => 'agencies.import',
            'export' => 'agencies.export',
            'viewHistory' => 'agencies.view_history',
            'manageStatus' => 'agencies.manage_status',
            default => null,
        };
    }
}
