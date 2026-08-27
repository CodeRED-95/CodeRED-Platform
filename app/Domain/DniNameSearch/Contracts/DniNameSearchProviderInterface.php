<?php

declare(strict_types=1);

namespace App\Domain\DniNameSearch\Contracts;

use App\Domain\DniNameSearch\Data\DniNameSearchResult;

interface DniNameSearchProviderInterface
{
    public function isEnabled(): bool;

    public function search(string $nombres, string $apellidoPaterno, string $apellidoMaterno): DniNameSearchResult;
}
