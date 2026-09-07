<?php

namespace App\DTO;

final readonly class SunatRepresentativeData
{
    public function __construct(
        public string $tipoDocumento,
        public string $numeroDocumento,
        public string $nombre,
        public string $cargo,
        public ?string $fechaDesde,
    ) {}

    public function toArray(): array
    {
        return [
            'tipo_documento' => $this->tipoDocumento,
            'numero_documento' => $this->numeroDocumento,
            'nombre' => $this->nombre,
            'cargo' => $this->cargo,
            'fecha_desde' => $this->fechaDesde,
        ];
    }
}
