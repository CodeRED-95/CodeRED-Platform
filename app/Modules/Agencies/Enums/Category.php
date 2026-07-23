<?php

namespace App\Modules\Agencies\Enums;

enum Category: string
{
    case MICRO = 'MICRO';
    case PEQUENA = 'PEQUEÑA';
    case MEDIANA = 'MEDIANA';
    case GRANDE_CO = 'GRANDE CO';
    case MINI_MICRO = 'MINI-MICRO';
    case MEXICO = 'MEXICO';
    case MICRO_ER = 'MICRO E/R';
    case LUNA_PIZARRO = 'LUNA PIZARRO';

    public function limitations(): string
    {
        return match ($this) {
            self::MICRO => 'Envía: 75 kg / 1 m³ | Recibe: 25 kg / 0.12 m³',
            self::PEQUENA => 'Envía: 75 kg / 1 m³ | Recibe: 75 kg / 1 m³',
            self::MEDIANA => 'Envía: 200 kg / 3 m³ | Recibe: 200 kg / 3 m³',
            self::GRANDE_CO => 'Envía: 1500 kg | Recibe: 1500 kg',
            self::MINI_MICRO => 'Envía: 75 kg | Recibe: Hasta paquete M',
            self::MEXICO => 'Envía: 1500 kg | Recibe: 75 kg / 1 m³',
            self::MICRO_ER => 'Envía: 25 kg / 0.5 m³ | Recibe: 25 kg / 0.12 m³',
            self::LUNA_PIZARRO => 'Envía: 1500 kg | No recibe (solo envíos)',
        };
    }
}
