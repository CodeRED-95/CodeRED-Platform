<?php

namespace App\Services\Events\Contracts;

interface UuidGeneratorContract
{
    public function generateV7(): string;
}
