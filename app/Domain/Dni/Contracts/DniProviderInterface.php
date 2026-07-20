<?php

namespace App\Domain\Dni\Contracts;

use App\Domain\Dni\Data\DniData;

interface DniProviderInterface
{
    public function find(string $dni): ?DniData;
}
