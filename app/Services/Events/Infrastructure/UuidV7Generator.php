<?php

namespace App\Services\Events\Infrastructure;

use App\Services\Events\Contracts\UuidGeneratorContract;
use Ramsey\Uuid\UuidFactory;

final class UuidV7Generator implements UuidGeneratorContract
{
    public function generateV7(): string
    {
        return (new UuidFactory())->uuid7()->toString();
    }
}
