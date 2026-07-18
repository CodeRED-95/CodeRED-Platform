<?php

use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Console\Command\Command;

Artisan::command('health:redis', function (): int {
    $key = 'codered_health_test_'.bin2hex(random_bytes(8));
    $original = [
        'cache' => config('cache.default'),
        'queue' => config('queue.default'),
        'session' => config('session.driver'),
        'client' => config('database.redis.client'),
    ];

    try {
        config()->set([
            'cache.default' => 'redis',
            'queue.default' => 'redis',
            'session.driver' => 'redis',
            'database.redis.client' => 'phpredis',
        ]);

        Cache::put($key, 'ok', 60);
        $value = Cache::get($key);
        Cache::forget($key);

        if ($value !== 'ok') {
            $this->error('Redis o la caché no respondieron correctamente.');

            return Command::FAILURE;
        }

        Redis::connection()->ping();
    } catch (Throwable $e) {
        $this->error('PhpRedis no respondió: '.$e->getMessage());

        return Command::FAILURE;
    } finally {
        config()->set([
            'cache.default' => $original['cache'],
            'queue.default' => $original['queue'],
            'session.driver' => $original['session'],
            'database.redis.client' => $original['client'],
        ]);
    }

    $this->info('Redis y la caché responden correctamente.');

    return Command::SUCCESS;
})->purpose('Verifica Redis y la caché de Laravel sin Tinker.');

Artisan::command('auth:diagnose {--email=}', function (): int {
    $email = trim((string) $this->option('email'));
    $user = auth()->user();

    if (! $user && $email !== '') {
        $user = User::query()->where('email', $email)->with('roles.permissions')->first();
    }

    if (! $user) {
        $this->error('No se encontró un usuario para diagnosticar.');

        return Command::FAILURE;
    }

    $roles = $user->roles()->pluck('slug')->values()->all();
    $permissions = $user->roles()->with('permissions')->get()->flatMap(fn ($role) => $role->permissions->pluck('slug'))->unique()->values()->all();
    $agency = Agency::query()->first();

    $this->line('Email: '.$user->email);
    $this->line('Roles: '.json_encode($roles, JSON_UNESCAPED_UNICODE));
    $this->line('Permisos: '.json_encode($permissions, JSON_UNESCAPED_UNICODE));
    $this->line("hasRole('super-admin'): ".($user->hasRole('super-admin') ? 'true' : 'false'));
    $this->line("can('agencies.view'): ".($user->can('agencies.view') ? 'true' : 'false'));
    $this->line("Gate::allows('viewAny', Agency::class): ".(Gate::forUser($user)->allows('viewAny', Agency::class) ? 'true' : 'false'));
    $this->line("Gate::allows('create', Agency::class): ".(Gate::forUser($user)->allows('create', Agency::class) ? 'true' : 'false'));

    if ($agency) {
        $this->line("Gate::allows('view', Agency#{$agency->id}): ".(Gate::forUser($user)->allows('view', $agency) ? 'true' : 'false'));
    }

    return Command::SUCCESS;
})->purpose('Diagnostica roles y permisos del usuario autenticado o de un correo indicado.');
