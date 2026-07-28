<?php

namespace App\Console\Commands;

use App\Enums\IntegrationStatus;
use App\Models\Integration;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class DeduplicateN8nIntegrationsCommand extends Command
{
    protected $signature = 'codered:n8n:deduplicate {--dry-run : Solo reporta duplicados sin modificar registros}';

    protected $description = 'Detecta y revoca duplicados seguros de integraciones n8n por instance_uuid o metadata legacy.';

    public function handle(IntegrationProtocolService $protocol): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $groups = Integration::query()
            ->where('provider', 'n8n')
            ->get()
            ->groupBy(fn (Integration $integration): string => $this->dedupeKey($integration));

        $duplicates = $groups->filter(fn (Collection $items): bool => $items->count() > 1);

        if ($duplicates->isEmpty()) {
            $this->info('No se encontraron integraciones n8n duplicadas.');

            return self::SUCCESS;
        }

        foreach ($duplicates as $key => $items) {
            $keeper = $items->sortByDesc(fn (Integration $integration): string => (string) ($integration->last_seen_at ?? $integration->updated_at ?? $integration->created_at))->first();
            $duplicateIds = $items->where('id', '!=', $keeper?->id)->pluck('id')->values()->all();
            $this->line(sprintf('%s conservar=%s duplicados=%s', $key, $keeper?->id, implode(',', $duplicateIds)));

            if ($dryRun || ! $keeper) {
                continue;
            }

            Integration::query()->whereIn('id', $duplicateIds)->update([
                'status' => IntegrationStatus::Revoked->value,
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($duplicateIds as $duplicateId) {
                $protocol->log($keeper, 'Deduplicate', 'Integración duplicada revocada.', ['duplicate_id' => $duplicateId, 'dedupe_key' => $key]);
            }
        }

        return self::SUCCESS;
    }

    private function dedupeKey(Integration $integration): string
    {
        $owner = (string) ($integration->created_by ?? 'system');
        $instanceUuid = (string) ($integration->getAttribute('instance_uuid') ?? '');

        if ($instanceUuid !== '') {
            return implode('|', [$owner, 'n8n', $instanceUuid]);
        }

        return implode('|', [$owner, 'n8n', strtolower(trim((string) $integration->instance_name)), strtolower(trim((string) $integration->instance_url))]);
    }
}
