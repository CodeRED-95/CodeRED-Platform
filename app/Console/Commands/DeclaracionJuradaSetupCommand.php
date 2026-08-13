<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Services\ApiTokens\ApiTokenGenerator;
use Illuminate\Console\Command;

class DeclaracionJuradaSetupCommand extends Command
{
    protected $signature = 'declaracion-jurada:setup {--reissue : Fuerza un token nuevo aunque las abilities ya coincidan}';

    protected $description = 'Crea (si falta) el ApiClient de Declaración Jurada Shalom y garantiza que su token tenga las abilities necesarias (dni:consultar, agencias:consultar)';

    /**
     * Abilities que el bridge Node de Declaración Jurada necesita hoy. Si se
     * amplía la integración (por ejemplo, una nueva consulta a CodeRED),
     * agregar la ability aquí es suficiente: este comando es idempotente y
     * reemite el token automáticamente la próxima vez que se ejecute (lo
     * hace update.sh en cada despliegue) sin necesitar --reissue manual.
     */
    private const ABILITIES = ['dni:consultar', 'agencias:consultar'];

    public function handle(ApiTokenGenerator $generator): int
    {
        $client = ApiClient::query()->firstOrCreate(
            ['name' => 'Declaración Jurada Shalom'],
            [
                'description' => 'Servicio Node/React independiente (packages/shalom-declaracion-jurada) que resuelve consultas DNI y agencias a través de la API de CodeRED Platform.',
                'active' => true,
            ]
        );

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
}
