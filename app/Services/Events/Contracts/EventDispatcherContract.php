<?php

namespace App\Services\Events\Contracts;

use App\Services\Events\DTO\EventData;

interface EventDispatcherContract
{
    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $type, array $payload = [], ?string $tenant = null, ?string $source = null): EventData;
}
