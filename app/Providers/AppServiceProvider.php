<?php

namespace App\Providers;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Domain\DniNameSearch\Contracts\DniNameSearchProviderInterface;
use App\Events\TokenRequestCreated;
use App\Listeners\SendTokenRequestCreatedWebhook;
use App\Models\ApiToken;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\UserPolicy;
use App\Services\Dni\PeruDevsDniProvider;
use App\Services\DniNameSearch\DniPeruNameSearchProvider;
use App\Services\Events\Contracts\EventDeliveryContract;
use App\Services\Events\Contracts\EventDispatcherContract;
use App\Services\Events\Contracts\EventDispatchRepositoryContract;
use App\Services\Events\Contracts\EventTransportContract;
use App\Services\Events\Contracts\UuidGeneratorContract;
use App\Services\Events\EventDispatcher;
use App\Services\Events\EventFactory;
use App\Services\Events\Infrastructure\AgentEventTransport;
use App\Services\Events\Infrastructure\EloquentEventDispatchRepository;
use App\Services\Events\Infrastructure\PlatformEventDelivery;
use App\Services\Events\Infrastructure\UuidV7Generator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(DniNameSearchProviderInterface::class, DniPeruNameSearchProvider::class);
        $this->app->singleton(UuidGeneratorContract::class, UuidV7Generator::class);
        $this->app->singleton(EventFactory::class);
        $this->app->singleton(EventDispatchRepositoryContract::class, EloquentEventDispatchRepository::class);
        $this->app->singleton(EventTransportContract::class, AgentEventTransport::class);
        $this->app->singleton(EventDeliveryContract::class, PlatformEventDelivery::class);
        $this->app->singleton(EventDispatcherContract::class, EventDispatcher::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);
        Event::listen(TokenRequestCreated::class, SendTokenRequestCreatedWebhook::class);
        User::observe(UserObserver::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super-admin') || $user->hasPermission($ability) ? true : null;
        });
        RateLimiter::for('api', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('api.rate_limit_per_minute'), 1), 'api'));
        RateLimiter::for('api-declaraciones', fn (Request $request): Limit => $this->tokenLimit($request, 30, 'declaraciones'));
        RateLimiter::for('api-agencias', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('api.agency_rate_limit_per_minute'), 1), 'agencias'));
        RateLimiter::for('api-mobile', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('api.mobile_rate_limit_per_minute'), 1), 'mobile'));
        RateLimiter::for('api-admin', fn (Request $request): Limit => $this->tokenLimit($request, 30, 'admin'));
        RateLimiter::for('api-dni', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('dni.rate_limit_per_minute'), 1), 'dni'));
        RateLimiter::for('api-dni-name-search', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('dni.name_search.rate_limit_per_minute'), 1), 'dni-name-search'));
        RateLimiter::for('ruc-lookup', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('ruc.rate_limit_per_minute'), 1), 'ruc'));
        RateLimiter::for('ruc-search', fn (Request $request): Limit => $this->tokenLimit($request, max((int) config('ruc.search_rate_limit_per_minute'), 1), 'ruc-search'));
        RateLimiter::for('ruc-admin-test', fn (Request $request): Limit => Limit::perMinute(20)->by('ruc-admin:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('public-token-request-form', fn (Request $request): Limit => Limit::perMinute(30)->by('token-form:'.hash_hmac('sha256', (string) $request->ip(), config('app.key'))));
        RateLimiter::for('public-token-requests', fn (Request $request): Limit => Limit::perHour((int) config('api.public_token_request_rate_limit', 5))->by('token-request:'.hash_hmac('sha256', implode('|', [(string) $request->ip(), (string) $request->input('installation_name'), (string) $request->input('delivery_destination')]), config('app.key'))));
        // Fuerza bruta sobre el login: se limita por IP y por correo a la vez,
        // de modo que ni un atacante desde una IP ni uno distribuido contra una
        // sola cuenta puedan probar contrasenas en volumen. El refresh es mas
        // holgado: es una operacion legitima y frecuente de los clientes.
        RateLimiter::for('auth-login', fn (Request $request): array => [
            Limit::perMinute(10)->by('auth-login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('auth-login-user:'.hash_hmac('sha256', mb_strtolower(trim((string) $request->input('email'))), config('app.key'))),
        ]);
        RateLimiter::for('auth-refresh', fn (Request $request): Limit => Limit::perMinute(30)->by('auth-refresh:'.$request->ip()));
        RateLimiter::for('shalom-recordar', fn (Request $request): Limit => $this->tokenLimit($request, 60, 'shalom-recordar'));
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
