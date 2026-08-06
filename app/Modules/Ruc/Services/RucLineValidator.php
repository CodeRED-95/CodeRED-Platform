<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Data\ValidationResult;
use App\Modules\Ruc\Data\ValidationContext;

class RucLineValidator
{
    /**
     * Valida una línea parseada
     */
    public function validate(
        array $fields,
        int $lineNumber,
        ValidationContext $context
    ): ValidationResult {
        $result = new ValidationResult();

        // Validar que tenemos campos suficientes
        if (count($fields) < 2) {
            $result->addError('Número de columnas insuficiente');
            return $result;
        }

        // Extraer campos
        $ruc = $fields[0] ?? null;
        $razonSocial = $fields[1] ?? null;
        $estado = $fields[2] ?? null;
        $condicion = $fields[3] ?? null;
        $ubigeo = $fields[4] ?? null;
        $direccion = $this->buildAddress(array_slice($fields, 5, 10));

        // Validar RUC
        if (!$this->validateRuc($ruc)) {
            $result->addError('RUC inválido (debe ser 11 dígitos)');
            return $result;
        }
        $result->ruc = $ruc;

        // Validar razón social
        if (!$this->validateRazonSocial($razonSocial)) {
            $result->addError('Razón social vacía o inválida');
            return $result;
        }

        // Validar estado
        if ($estado !== null && $estado !== '' && !$this->validateState($estado, $context->allowedStates)) {
            $result->addWarning("Estado no reconocido: {$estado}");
        }

        // Validar condición
        if ($condicion !== null && $condicion !== '' && !$this->validateCondition($condicion, $context->allowedConditions)) {
            $result->addWarning("Condición no reconocida: {$condicion}");
        }

        // Validar UBIGEO (formato, no existencia)
        if ($ubigeo !== null && $ubigeo !== '' && !$this->validateUbigeoFormat($ubigeo)) {
            $result->addWarning("UBIGEO con formato inválido: {$ubigeo}");
            $ubigeo = null;
        }

        // Validar dirección
        if ($direccion !== null && strlen($direccion) > 500) {
            $result->addWarning('Dirección truncada (máximo 500 caracteres)');
            $direccion = substr($direccion, 0, 500);
        }

        // Detectar duplicados dentro del archivo
        if (isset($context->seenRucs[$ruc])) {
            $result->isDuplicate = true;
            $result->firstOccurrence = $context->seenRucs[$ruc];
            return $result;
        }

        // Marcar RUC como visto
        $context->seenRucs[$ruc] = $lineNumber;

        // Construir datos validados
        $result->data = [
            'ruc' => $ruc,
            'razon_social' => $razonSocial,
            'estado' => $estado ?: null,
            'condicion' => $condicion ?: null,
            'ubigeo' => $ubigeo,
            'direccion' => $direccion,
        ];

        $result->valid = true;
        return $result;
    }

    /**
     * Valida formato de RUC (11 dígitos)
     */
    private function validateRuc(?string $ruc): bool
    {
        if ($ruc === null || $ruc === '') {
            return false;
        }

        return preg_match('/^\d{11}$/', trim($ruc)) === 1;
    }

    /**
     * Valida razón social
     */
    private function validateRazonSocial(?string $razonSocial): bool
    {
        if ($razonSocial === null || $razonSocial === '') {
            return false;
        }

        $trimmed = trim($razonSocial);
        if ($trimmed === '' || $trimmed === '-' || strtoupper($trimmed) === 'NULL') {
            return false;
        }

        if (strlen($trimmed) > 500) {
            return false;
        }

        return true;
    }

    /**
     * Valida estado contra lista permitida
     */
    private function validateState(?string $state, array $allowedStates): bool
    {
        if ($state === null || $state === '') {
            return true;
        }

        if (empty($allowedStates)) {
            return true; // Sin lista: aceptar cualquiera
        }

        return in_array(strtoupper(trim($state)), array_map('strtoupper', $allowedStates), true);
    }

    /**
     * Valida condición contra lista permitida
     */
    private function validateCondition(?string $condition, array $allowedConditions): bool
    {
        if ($condition === null || $condition === '') {
            return true;
        }

        if (empty($allowedConditions)) {
            return true; // Sin lista: aceptar cualquiera
        }

        return in_array(strtoupper(trim($condition)), array_map('strtoupper', $allowedConditions), true);
    }

    /**
     * Valida formato de UBIGEO (6 dígitos)
     */
    private function validateUbigeoFormat(?string $ubigeo): bool
    {
        if ($ubigeo === null || $ubigeo === '') {
            return true;
        }

        return preg_match('/^\d{6}$/', trim($ubigeo)) === 1;
    }

    /**
     * Construye dirección desde partes
     */
    private function buildAddress(array $parts): ?string
    {
        $cleaned = array_map(fn ($p) => is_string($p) ? trim($p) : '', $parts);
        $cleaned = array_filter($cleaned, fn ($p) => $p !== '' && $p !== '-' && strtoupper($p) !== 'NULL');

        if (empty($cleaned)) {
            return null;
        }

        return implode(' ', $cleaned);
    }

    /**
     * Obtiene lista de estados permitidos (SUNAT)
     */
    public static function getAllowedStates(): array
    {
        return [
            'ACTIVO',
            'INACTIVO',
            'SUSPENSIÓN TEMPORAL',
            'CANCELADO',
            'HABIDO',
            'NO HABIDO',
            'ELIMINADO DE OFICIO',
            'SOLICITÓ CANCELACIÓN',
        ];
    }

    /**
     * Obtiene lista de condiciones permitidas (SUNAT)
     */
    public static function getAllowedConditions(): array
    {
        return [
            'ACTIVO',
            'INACTIVO',
            'NO HABIDO',
            'CANCELADO POR ACUERDO',
            'CANCELADO DE OFICIO',
            'SOLICITÓ CANCELACIÓN',
            'HABIDO',
        ];
    }
}
