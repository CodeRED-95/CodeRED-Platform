<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Services\ApiTokens\ApiTokenGenerator;
use Illuminate\Console\Command;

class DeclaracionJuradaSetupCommand extends Command
{
    protected $signature = 'declaracion-jurada:setup {--reissue : Revoca el token actual y emite uno nuevo}';

    protected $description = 'Crea (si falta) el ApiClient de Declaración Jurada Shalom y emite su token dni:consultar';

    public function handle(ApiTokenGenerator $generator): int
    {
        $client = ApiClient::query()->firstOrCreate(
            ['name' => 'Declaración Jurada Shalom'],
            [
                'description' => 'Servicio Node/React independiente (packages/shalom-declaracion-jurada) que resuelve consultas DNI a través de la API de CodeRED Platform.',
                'active' => true,
            ]
        );

        $activeToken = $client->tokens()->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($activeToken && ! $this->option('reissue')) {
            $this->info("El cliente API '{$client->name}' ya tiene un token activo (id {$activeToken->id}).");
            $this->line('Para emitir uno nuevo (y revocar el actual) use: php artisan declaracion-jurada:setup --reissue');
            $this->line('También puede gestionar sus tokens desde Admin → API y Tokens en el panel.');

            return self::SUCCESS;
        }

        if ($activeToken) {
            $activeToken->forceFill(['revoked_at' => now()])->save();
            $this->warn("Token anterior (id {$activeToken->id}) revocado.");
        }

        $created = $generator->create($client, 'declaracion-jurada-dni-bridge', ['dni:consultar'], ApiTokenGenerator::MAX_EXPIRES_IN_DAYS);

        $this->info("Token emitido para '{$client->name}':");
        $this->line($created->plainTextToken);
        $this->newLine();
        $this->warn('Este valor solo se muestra una vez. Guárdelo como DECLARACION_JURADA_CODERED_API_TOKEN en .env.');

        return self::SUCCESS;
    }
}
