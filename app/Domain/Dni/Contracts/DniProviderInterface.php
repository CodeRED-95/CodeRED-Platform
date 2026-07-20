<?php

namespace App\Domain\Dni\Contracts;

use App\Domain\Dni\Data\DniProviderResult;

interface DniProviderInterface
{
    public function isEnabled(): bool;

    public function find(string $dni): DniProviderResult;
}
