<?php

namespace App\Domain\Dni\Data;

use Carbon\CarbonImmutable;
use Throwable;

final readonly class DniData
{
    public function __construct(
        public string $dni,
        public string $nombreCompleto,
        public string $nombres,
        public string $apellidoPaterno,
        public string $apellidoMaterno,
        public ?string $genero = null,
        public ?string $fechaNacimiento = null,
        public ?string $codigoVerificacion = null,
        public ?string $providerReference = null,
    ) {}

    public function age(): ?int
    {
        if ($this->fechaNacimiento === null) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->fechaNacimiento)->startOfDay()->age;
        } catch (Throwable) {
            return null;
        }
    }

    public function toArray(): array
    {
        return [
            'dni' => $this->dni,
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'nombre_completo' => $this->nombreCompleto,
            'genero' => $this->genero,
            'fecha_nacimiento' => $this->fechaNacimiento,
            'edad' => $this->age(),
            'codigo_verificacion' => $this->codigoVerificacion,
            'provider_reference' => $this->providerReference,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['dni'],
            (string) $data['nombre_completo'],
            (string) $data['nombres'],
            (string) $data['apellido_paterno'],
            (string) $data['apellido_materno'],
            isset($data['genero']) ? (string) $data['genero'] : null,
            isset($data['fecha_nacimiento']) ? (string) $data['fecha_nacimiento'] : null,
            isset($data['codigo_verificacion']) ? (string) $data['codigo_verificacion'] : null,
            isset($data['provider_reference']) ? (string) $data['provider_reference'] : null,
        );
    }
}
