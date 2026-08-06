<?php

namespace App\Modules\Ruc\Data;

class ValidationContext
{
    public array $seenRucs = [];
    public array $ubigeos = [];
    public array $allowedStates = [];
    public array $allowedConditions = [];

    public function __construct()
    {
        $this->allowedStates = [
            'ACTIVO',
            'INACTIVO',
            'SUSPENSIÓN TEMPORAL',
            'CANCELADO',
            'HABIDO',
            'NO HABIDO',
            'ELIMINADO DE OFICIO',
            'SOLICITÓ CANCELACIÓN',
        ];

        $this->allowedConditions = [
            'ACTIVO',
            'INACTIVO',
            'NO HABIDO',
            'CANCELADO POR ACUERDO',
            'CANCELADO DE OFICIO',
            'SOLICITÓ CANCELACIÓN',
            'HABIDO',
        ];
    }

    public function addSeenRuc(string $ruc, int $lineNumber): void
    {
        $this->seenRucs[$ruc] = $lineNumber;
    }

    public function hasSeenRuc(string $ruc): bool
    {
        return isset($this->seenRucs[$ruc]);
    }

    public function getFirstLineForRuc(string $ruc): ?int
    {
        return $this->seenRucs[$ruc] ?? null;
    }

    public function setUbigeos(array $ubigeos): void
    {
        $this->ubigeos = $ubigeos;
    }

    public function hasUbigeo(string $codigo): bool
    {
        return isset($this->ubigeos[$codigo]);
    }

    public function getUbigeo(string $codigo): ?array
    {
        return $this->ubigeos[$codigo] ?? null;
    }
}
