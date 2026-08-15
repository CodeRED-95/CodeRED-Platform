<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ApiTokens\ApiTokenGenerator;
use Illuminate\Console\Command;

class DeclaracionJuradaSetupCommand extends Command
{
    protected $signature = 'declaracion-jurada:setup {--reissue : Fuerza un token nuevo aunque las abilities ya coincidan}';

    protected $description = 'Garantiza el acceso RBAC (permiso declaracion-jurada.view en el rol viewer) y el ApiClient/token de Declaración Jurada Shalom';

    /**
     * Abilities que el bridge Node de Declaración Jurada necesita hoy. Si se
     * amplía la integración (por ejemplo, una nueva consulta a CodeRED),
     * agregar la ability aquí es suficiente: este comando es idempotente y
     * reemite el token automáticamente la próxima vez que se ejecute (lo
     * hace update.sh en cada despliegue) sin necesitar --reissue manual.
     */
    private const ABILITIES = ['dni:consultar', 'agencias:consultar', 'declaraciones:gestionar'];

    /**
     * Permiso que ResolveDelegatedUser exige al usuario indicado en
     * X-CodeRED-User-Id. El bridge Node autentica con su token técnico, pero
     * cada declaración se atribuye —y se autoriza— contra el usuario real de
     * CodeRED que tiene la sesión abierta en el paquete React, que es el mismo
     * permiso que ya le habilita el login allí.
     */
    private const DELEGATION_PERMISSION = 'declaracion-jurada.view';

    /**
     * Roles de CodeRED que deben tener acceso de lectura a Declaración
     * Jurada por defecto (permiso declaracion-jurada.view), sin asignación
     * manual por usuario. super-admin ya lo tiene siempre vía el bypass de
     * User::hasPermission() — no hace falta listarlo aquí.
     */
    private const ROLES_WITH_VIEW_ACCESS = ['viewer'];

    public function handle(ApiTokenGenerator $generator): int
    {
        $this->ensureRoleAccess();

        $client = ApiClient::query()->firstOrCreate(
            ['name' => 'Declaración Jurada Shalom'],
            [
                'description' => 'Servicio Node/React independiente (packages/shalom-declaracion-jurada) que resuelve consultas DNI, agencias y declaraciones juradas a través de la API de CodeRED Platform.',
                'active' => true,
            ]
        );

        $this->ensureDelegation($client);

        $activeToken = $client->tokens()->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        $abilitiesMatch = $activeToken
            && $this->sameAbilities($activeToken->abilities ?? [], self::ABILITIES);

        if ($activeToken && $abilitiesMatch && ! $this->option('reissue')) {
            $this->info("El cliente API '{$client->name}' ya tiene un token activo (id {$activeToken->id}) con las abilities correctas: ".implode(', ', self::ABILITIES));
            $this->line('Para forzar uno nuevo de todas formas use: php artisan declaracion-jurada:setup --reissue');

            return self::SUCCESS;
        }

        if ($activeToken) {
            $activeToken->forceFill(['revoked_at' => now()])->save();
            $reason = $abilitiesMatch ? 'reemisión forzada' : 'abilities desactualizadas';
            $this->warn("Token anterior (id {$activeToken->id}) revocado ({$reason}).");
        }

        $created = $generator->create($client, 'declaracion-jurada-dni-bridge', self::ABILITIES, ApiTokenGenerator::MAX_EXPIRES_IN_DAYS);

        $this->info("Token emitido para '{$client->name}' con abilities [".implode(', ', self::ABILITIES).']:');
        $this->line($created->plainTextToken);
        $this->newLine();
        $this->warn('Este valor solo se muestra una vez. Guárdelo como DECLARACION_JURADA_CODERED_API_TOKEN en .env.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $current @param array<int, string> $expected */
    private function sameAbilities(array $current, array $expected): bool
    {
        sort($current);
        $expectedSorted = $expected;
        sort($expectedSorted);

        return $current === $expectedSorted;
    }

    /**
     * Habilita la delegación de identidad en el ApiClient (firstOrCreate no
     * actualiza instalaciones previas, creadas antes de que existieran estas
     * columnas). Sin esto, ResolveDelegatedUser rechaza X-CodeRED-User-Id con
     * 403 y todas las declaraciones del paquete React fallarían.
     */
    private function ensureDelegation(ApiClient $client): void
    {
        if ($client->canDelegateUsers() && $client->delegation_permission === self::DELEGATION_PERMISSION) {
            return;
        }

        $client->forceFill([
            'can_delegate_users' => true,
            'delegation_permission' => self::DELEGATION_PERMISSION,
        ])->save();

        $this->info('Delegación de identidad habilitada (X-CodeRED-User-Id, exige '.self::DELEGATION_PERMISSION.').');
    }

    /**
     * Garantiza que declaracion-jurada.view exista y que los roles de
     * ROLES_WITH_VIEW_ACCESS lo tengan — sin tocar el resto de sus
     * permisos. A diferencia de PermissionsSeeder (que usa sync() y por lo
     * tanto reemplaza la lista completa de permisos del rol), aquí se usa
     * syncWithoutDetaching(): es aditivo, así que ejecutar este comando no
     * puede quitarle a viewer ningún permiso que ya tuviera por otra vía.
     * Es la misma garantía tanto en una instalación existente como en una
     * nueva, y corre en cada `declaracion-jurada:setup` (idempotente: no
     * duplica filas en permission_role ni permissions).
     */
    private function ensureRoleAccess(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'declaracion-jurada.view'],
            ['name' => 'Ver Declaración Jurada']
        );

        foreach (self::ROLES_WITH_VIEW_ACCESS as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if ($role === null) {
                $this->warn("Rol '{$roleSlug}' no existe todavía; se omite la asignación de declaracion-jurada.view (correrá en el próximo intento).");

                continue;
            }

            if ($role->permissions()->where('permissions.id', $permission->id)->exists()) {
                $this->info("El rol '{$roleSlug}' ya tiene declaracion-jurada.view.");

                continue;
            }

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $this->info("Permiso declaracion-jurada.view asignado al rol '{$roleSlug}'.");
        }
    }
}
