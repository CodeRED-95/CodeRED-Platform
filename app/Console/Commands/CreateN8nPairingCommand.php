<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Console\Command;

class CreateN8nPairingCommand extends Command
{
    protected $signature = 'integrations:n8n-pair-code {--json : Output as JSON}';

    protected $description = 'Genera un codigo temporal de pairing para CodeRED Agent o n8n.';

    public function handle(IntegrationProtocolService $protocol): int
    {
        $pairing = $protocol->createPairing('n8n');
        $payload = [
            'pair_code' => $pairing->pair_code,
            'expires_at' => $pairing->expiresAt()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Código de pairing generado correctamente.');
        $this->line('Código: '.$payload['pair_code']);
        $this->line('Expira: '.$payload['expires_at']);
        $this->warn('No se generó ni se mostró ningún secreto. Use este código una sola vez.');

        return self::SUCCESS;
    }
}
