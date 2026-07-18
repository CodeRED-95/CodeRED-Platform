<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('health:redis', function (): int {
    $key = 'codered_health_test_'.bin2hex(random_bytes(8));

    Cache::put($key, 'ok', 60);
    $value = Cache::get($key);
    Cache::forget($key);

    if ($value !== 'ok') {
        $this->error('Redis o la caché no respondieron correctamente.');

        return Command::FAILURE;
    }

    try {
        Redis::connection()->ping();
    } catch (\Throwable $e) {
        $this->error('PhpRedis no respondió: '.$e->getMessage());

        return Command::FAILURE;
    }

    $this->info('Redis y la caché responden correctamente.');

    return Command::SUCCESS;
})->purpose('Verifica Redis y la caché de Laravel sin Tinker.');
