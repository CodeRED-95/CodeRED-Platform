<?php

declare(strict_types=1);

namespace App\Domain\DniNameSearch\Data;

final readonly class DniNameMatch
{
    public function __construct(
        public string $dni,
        public string $nombres,
        public string $apellidoPaterno,
        public string $apellidoMaterno,
    ) {}

    public function toArray(): array
    {
        return [
            'dni' => $this->dni,
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'full_name' => trim("{$this->nombres} {$this->apellidoPaterno} {$this->apellidoMaterno}"),
        ];
    }
}
