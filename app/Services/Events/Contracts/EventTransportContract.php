<?php

namespace App\Services\Events\Contracts;

use App\Services\Events\DTO\EventData;

interface EventTransportContract
{
    public function send(EventData $event): bool;
}
