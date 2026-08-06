<?php

namespace App\Modules\Ruc\Enums;

enum MergeStrategy: string
{
    case Insert = 'insert';
    case InsertUpdate = 'insert_update';
    case Replace = 'replace';

    public function label(): string
    {
        return match ($this) {
            self::Insert => 'Solo insertar nuevos',
            self::InsertUpdate => 'Insertar o actualizar',
            self::Replace => 'Reemplazar completamente',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Insert => 'Solo inserta registros nuevos. Los existentes no se modifican.',
            self::InsertUpdate => 'Inserta registros nuevos o actualiza los existentes.',
            self::Replace => 'Reemplaza completamente todos los registros.',
        };
    }
}
