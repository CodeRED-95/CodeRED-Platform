<?php

namespace App\Services\Events\Contracts;

use App\Models\EventDispatch;
use App\Services\Events\DTO\EventData;

interface EventDeliveryContract
{
    public function deliver(EventDispatch $dispatch, EventData $event): bool;
}
