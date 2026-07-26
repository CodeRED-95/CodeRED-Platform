<?php

namespace App\Console\Commands;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportItem;
use App\Modules\Agencies\Services\AgencyPlaceGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RepairAgencyLocationFieldsCommand extends Command
{
    protected $signature = 'agencies:repair-location-fields
        {--dry-run : Genera la vista previa sin modificar agencias}
        {--apply : Aplica únicamente distritos confirmados por importaciones recientes}
        {--report= : Ruta relativa del reporte JSON en el disco local}';

    protected $description = 'Revisa distritos vacíos y repara ubicaciones usando datos confirmados, sin copiar zone automáticamente';

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Usa --dry-run o --apply, no ambos.');

            return self::INVALID;
        }

        $candidates = $this->candidates();
        $confirmed = $candidates->where('source', 'recent_import')->values();
        $historicalOnly = $candidates->where('source', 'historical_zone_unconfirmed')->values();

        $this->table(
            ['ID', 'Código', 'Agencia', 'Distrito actual', 'Distrito propuesto', 'Fuente', 'Place propuesto'],
            $candidates->map(fn (array $item): array => [
                $item['agency_id'],
                $item['code'],
                $item['name'],
                $item['current_district'] ?? '—',
                $item['proposed_district'] ?? '—',
                $item['source'] === 'recent_import' ? 'Importación reciente' : 'zone histórico (no confirmado)',
                $item['proposed_place'] ?? '—',
            ])->all(),
        );

        $applied = [];
        if ($this->option('apply') && $confirmed->isNotEmpty()) {
            if (! $this->confirm('¿Aplicar los distritos confirmados por importaciones recientes y regenerar place?', false)) {
                $this->warn('Aplicación cancelada. No se modificó ninguna agencia.');
            } else {
                DB::transaction(function () use ($confirmed, &$applied): void {
                    foreach ($confirmed as $candidate) {
                        $agency = Agency::query()->lockForUpdate()->findOrFail($candidate['agency_id']);
                        if (filled($agency->district)) {
                            continue;
                        }

                        $agency->district = $candidate['proposed_district'];
                        $agency->save();
                        $applied[] = $agency->id;
                    }
                }, 3);
            }
        }

        $reportPath = $this->reportPath();
        Storage::disk('local')->put($reportPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'summary' => [
                'empty_district_count' => $candidates->count(),
                'confirmed_from_recent_import' => $confirmed->count(),
                'historical_zone_requires_manual_review' => $historicalOnly->count(),
                'applied_count' => count($applied),
            ],
            'applied_agency_ids' => $applied,
            'candidates' => $candidates->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info('Reporte JSON: '.$reportPath);
        $this->line($confirmed->count().' agencias tienen un distrito confirmado por una importación reciente.');
        $this->line($historicalOnly->count().' agencias solo tienen zone histórico y requieren revisión manual.');
        $this->line(count($applied).' agencias fueron actualizadas.');

        return self::SUCCESS;
    }

    private function candidates(): Collection
    {
        return Agency::query()
            ->where(fn ($query) => $query->whereNull('district')->orWhere('district', ''))
            ->orderBy('id')
            ->get()
            ->map(function (Agency $agency): array {
                $importItem = AgencyImportItem::query()
                    ->where('matched_agency_id', $agency->id)
                    ->whereNotNull('incoming_data->district')
                    ->latest('id')
                    ->first();
                $importDistrict = $this->clean($importItem?->incoming_data['district'] ?? null);
                $historicalZone = $this->clean($agency->getAttribute('zone'));
                $proposedPlace = null;

                if ($importDistrict !== null) {
                    $probe = $agency->replicate();
                    $probe->district = $importDistrict;
                    $proposedPlace = app(AgencyPlaceGenerator::class)($probe);
                }

                return [
                    'agency_id' => $agency->id,
                    'external_id' => $agency->external_id,
                    'code' => $agency->code,
                    'name' => $agency->name,
                    'current_district' => $this->clean($agency->district),
                    'proposed_district' => $importDistrict,
                    'source' => $importDistrict !== null ? 'recent_import' : 'historical_zone_unconfirmed',
                    'historical_zone' => $historicalZone,
                    'proposed_place' => $proposedPlace,
                    'import_item_id' => $importItem?->id,
                    'import_run_id' => $importItem?->import_run_id,
                ];
            });
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    private function reportPath(): string
    {
        $requested = trim((string) $this->option('report'), '/');

        return $requested !== ''
            ? $requested
            : 'reports/agencies-location-repair-'.now()->format('Ymd-His').'.json';
    }
}
