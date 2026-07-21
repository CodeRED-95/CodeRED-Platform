<?php

namespace App\Modules\Ruc\Data;

use App\Modules\Ruc\Models\RucRecord;

final readonly class RucData
{
    public function __construct(
        public string $ruc,
        public string $razonSocial,
        public ?string $estado,
        public ?string $condicion,
        public ?string $ubigeo,
        public ?string $direccion,
        public ?string $departamento,
        public ?string $provincia,
        public ?string $distrito,
    ) {}

    public static function fromModel(RucRecord $record): self
    {
        return new self($record->ruc, $record->razon_social, $record->estado, $record->condicion, $record->ubigeo, $record->direccion, $record->departamento, $record->provincia, $record->distrito);
    }

    public function toArray(): array
    {
        return ['ruc' => $this->ruc, 'razon_social' => $this->razonSocial, 'estado' => $this->estado, 'condicion' => $this->condicion, 'ubigeo' => $this->ubigeo, 'direccion' => $this->direccion, 'departamento' => $this->departamento, 'provincia' => $this->provincia, 'distrito' => $this->distrito];
    }
}
