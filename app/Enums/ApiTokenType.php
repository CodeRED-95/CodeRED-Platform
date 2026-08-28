<?php

namespace App\Enums;

enum ApiTokenType: string
{
    case Dni = 'dni';
    case Ruc = 'ruc';
    case Agencies = 'agencies';
    case Anime = 'anime';
    case ShalomRecordar = 'shalom-recordar';

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'Token DNI',
            self::Ruc => 'Token RUC',
            self::Agencies => 'Token AGENCIAS',
            self::Anime => 'Token ANIME',
            self::ShalomRecordar => 'Token SHALOM RECORDAR',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Dni => 'Permite consultar datos por DNI.',
            self::Ruc => 'Permite consultar RUC por numero y buscar por razon social.',
            self::Agencies => 'Permite consultar agencias, catalogo compatible y datos cartograficos.',
            self::Anime => 'Permite consultar catalogo, metadata, episodios y streams de CodeRED Anime.',
            self::ShalomRecordar => 'Permite sincronizar datos de Shalom Recordar Extension con CodeRED Platform.',
        };
    }

    /**
     * Ability que lleva todo token, sea del tipo que sea.
     *
     * `profile:read` solo permite preguntar "quien soy y que puedo hacer": el
     * endpoint /me devuelve el propietario del token y su propia lista de
     * abilities, nada mas. No concede acceso a ningun dato de negocio.
     *
     * Va en todos los tipos porque un cliente necesita validar el token antes de
     * usarlo. Sin ella, un token emitido desde el flujo de solicitudes recibia un
     * 403 al comprobarse y resultaba inservible aunque tuviera sus permisos
     * funcionales correctos.
     */
    public const BASE_ABILITY = 'profile:read';

    /** @return list<string> */
    public function abilities(): array
    {
        $funcionales = match ($this) {
            self::Dni => ['dni:consultar'],
            self::Ruc => ['ruc:consultar', 'ruc:buscar'],
            self::Agencies => ['agencies:read'],
            self::Anime => ['anime:read'],
            self::ShalomRecordar => ['shalom-recordar:sync'],
        };

        return array_values(array_unique([...$funcionales, self::BASE_ABILITY]));
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
