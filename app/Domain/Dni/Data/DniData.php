<?php

namespace App\Domain\Dni\Data;

final readonly class DniData
{
    public function __construct(public string $dni, public string $nombreCompleto, public string $nombres, public string $apellidoPaterno, public string $apellidoMaterno, public ?string $fechaNacimiento = null, public ?int $edad = null) {}

    public function toArray(): array
    {
        return ['dni' => $this->dni, 'nombre_completo' => $this->nombreCompleto, 'nombres' => $this->nombres, 'apellido_paterno' => $this->apellidoPaterno, 'apellido_materno' => $this->apellidoMaterno, 'fecha_nacimiento' => $this->fechaNacimiento, 'edad' => $this->edad];
    }

    public static function fromArray(array $data): self
    {
        return new self((string) $data['dni'], (string) $data['nombre_completo'], (string) $data['nombres'], (string) $data['apellido_paterno'], (string) $data['apellido_materno'], isset($data['fecha_nacimiento']) ? (string) $data['fecha_nacimiento'] : null, isset($data['edad']) ? (int) $data['edad'] : null);
    }
}
