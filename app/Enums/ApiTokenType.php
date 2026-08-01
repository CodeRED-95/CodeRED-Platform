<?php

namespace App\Enums;

enum ApiTokenType: string
{
    case Dni = 'dni';
    case Ruc = 'ruc';
    case Agencies = 'agencies';

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'Token DNI',
            self::Ruc => 'Token RUC',
            self::Agencies => 'Token AGENCIAS',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Dni => 'Permite consultar datos por DNI.',
            self::Ruc => 'Permite consultar RUC por numero y buscar por razon social.',
            self::Agencies => 'Permite consultar agencias, catalogo compatible y datos cartograficos.',
        };
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return match ($this) {
            self::Dni => ['dni:consultar'],
            self::Ruc => ['ruc:consultar', 'ruc:buscar'],
            self::Agencies => ['agencies:read'],
        };
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    /** @return list<string> */
    public static function allowedAbilities(): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn (self $type): array => $type->abilities(),
            self::cases(),
        ))));
    }

    /** @return array<int, array{value: string, label: string, description: string, abilities: list<string>}> */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'abilities' => $type->abilities(),
            ],
            self::cases(),
        );
    }
}
