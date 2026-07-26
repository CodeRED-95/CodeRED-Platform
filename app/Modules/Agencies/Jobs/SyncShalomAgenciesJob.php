<?php

namespace App\Modules\Agencies\Jobs;

use App\Modules\Agencies\Actions\UpdateAgencyNameAction;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportItem;
use App\Modules\Agencies\Models\AgencyImportRun;
use App\Modules\Agencies\Services\AgencyMapUrlGenerator;
use App\Modules\Agencies\Services\AgencyPlaceGenerator;
use App\Modules\Agencies\Services\ChosenFileParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SyncShalomAgenciesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;

    public function __construct(public int $importRunId, public string $chosenPath) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('shalom-agencies-sync'))->expireAfter(600)];
    }

    public function handle(ChosenFileParser $chosenParser, AgencyPlaceGenerator $placeGenerator, AgencyMapUrlGenerator $mapUrlGenerator, UpdateAgencyNameAction $nameAction): void
    {
        $run = AgencyImportRun::query()->findOrFail($this->importRunId);

        if (! config('services.shalom_extractor.enabled', true)) {
            throw new RuntimeException('El extractor Shalom está deshabilitado. Revisa SHALOM_EXTRACTOR_ENABLED.');
        }

        $run->update(['status' => 'running', 'stage' => 'Extrayendo agencias Shalom', 'started_at' => now(), 'progress' => 10]);

        try {
            $chosenFileContent = Storage::get($this->chosenPath);
            $chosenRows = collect($chosenParser($chosenFileContent))->keyBy('external_id');

            $timeout = (int) config('services.shalom_extractor.timeout', 180);
            $url = rtrim((string) config('services.shalom_extractor.url', 'http://shalom-extractor:3000'), '/');

            $response = Http::connectTimeout(15)->timeout($timeout)->retry(2, 1500)->post($url.'/extract', [
                'chosenFileContent' => $chosenFileContent,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('El extractor Shalom respondió con estado '.$response->status().'.');
            }

            $payload = $response->json();
            $incomingRows = collect($payload['agencies'] ?? $payload)->filter(fn ($row) => is_array($row))->values();

            $basePath = 'imports/shalom/'.$run->id;
            Storage::append($basePath.'/extractor.log', '['.now()->toIso8601String().'] Respuesta recibida. Total reportado: '.($payload['total'] ?? 'desconocido').'. Diagnóstico: '.json_encode($payload['diagnostics'] ?? [], JSON_UNESCAPED_UNICODE));

            if ($incomingRows->isEmpty()) {
                throw new RuntimeException('El extractor respondió correctamente, pero no encontró agencias. Revisa extractor.log y los logs del contenedor codered-shalom-extractor; la ejecución no se marcará como lista para revisión.');
            }
            $processedRows = [];
            $counts = ['new_count' => 0, 'updated_count' => 0, 'renamed_count' => 0, 'unchanged_count' => 0, 'conflict_count' => 0, 'missing_count' => 0, 'error_count' => 0];

            $run->items()->delete();
            $run->update(['stage' => 'Preparando vista previa', 'progress' => 50, 'total_received' => $incomingRows->count()]);

            foreach ($incomingRows as $row) {
                $incoming = $this->normalizeIncoming($row);
                if ($incoming['external_id'] && $chosenRows->has($incoming['external_id'])) {
                    $incoming = array_merge($incoming, Arr::only($chosenRows[$incoming['external_id']], ['texto_chosen_terrestre', 'texto_chosen_aereo']));
                }

                $probe = new Agency($incoming);
                $incoming['place'] = $placeGenerator($probe);
                $incoming['map_url'] = $mapUrlGenerator($probe);

                [$agency, $conflictReason] = $this->matchAgency($incoming);
                [$action, $differences, $proposedOldName] = $this->classify($agency, $incoming, $conflictReason, $nameAction);
                $counts[$this->counterFor($action)]++;

                AgencyImportItem::create([
                    'import_run_id' => $run->id,
                    'external_id' => $incoming['external_id'],
                    'matched_agency_id' => $agency?->id,
                    'action' => $action,
                    'confidence' => $agency ? 100 : 0,
                    'incoming_data' => $incoming,
                    'current_data' => $agency?->only(array_keys($incoming)),
                    'differences' => $differences,
                    'proposed_old_name' => $proposedOldName,
                    'conflict_reason' => $conflictReason,
                    'selected' => ! in_array($action, ['conflict', 'unchanged', 'invalid'], true),
                ]);

                $processedRows[] = $incoming;
            }

            Storage::put($basePath.'/agencies-processed.json', json_encode($processedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            Storage::put($basePath.'/report.json', json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $run->update(array_merge($counts, [
                'status' => 'ready_for_review',
                'stage' => 'Lista para revisión',
                'progress' => 100,
                'finished_at' => now(),
                'total_processed' => count($processedRows),
                'extracted_json_path' => $basePath.'/agencies-processed.json',
                'report_json_path' => $basePath.'/report.json',
            ]));
        } catch (Throwable $exception) {
            Storage::append('imports/shalom/'.$run->id.'/extractor.log', '['.now()->toIso8601String().'] ERROR: '.$exception->getMessage());

            $run->update([
                'status' => 'failed',
                'stage' => 'Error',
                'finished_at' => now(),
                'error_count' => 1,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function normalizeIncoming(array $row): array
    {
        $schedule = $row['schedule'] ?? [];
        $classification = $row['classification'] ?? [];

        return [
            'external_id' => filled($row['external_id'] ?? null) ? (int) $row['external_id'] : null,
            'code' => $this->clean($row['code'] ?? null),
            'name' => $this->clean($row['name'] ?? null),
            'department' => $this->clean($row['department'] ?? null),
            'province' => $this->clean($row['province'] ?? null),
            'district' => $this->clean($row['district'] ?? null),
            'address' => $this->clean($row['address'] ?? null),
            'latitude' => is_numeric($row['latitude'] ?? null) ? (string) $row['latitude'] : null,
            'longitude' => is_numeric($row['longitude'] ?? null) ? (string) $row['longitude'] : null,
            'schedule_general' => $this->clean($schedule['general'] ?? $row['schedule_general'] ?? null),
            'schedule_sunday' => $this->clean($schedule['sunday'] ?? $row['schedule_sunday'] ?? null),
            'classification_category' => $this->clean($classification['category'] ?? $row['classification_category'] ?? null),
            'classification_sends_category' => $this->clean($classification['sends_category'] ?? $row['classification_sends_category'] ?? null),
            'classification_receives_category' => $this->clean($classification['receives_category'] ?? $row['classification_receives_category'] ?? null),
            'texto_chosen_terrestre' => $this->clean($row['chosen_terrestre'] ?? $row['texto_chosen_terrestre'] ?? null),
            'texto_chosen_aereo' => $this->clean($row['chosen_aereo'] ?? $row['texto_chosen_aereo'] ?? null),
        ];
    }

    private function matchAgency(array $incoming): array
    {
        if ($incoming['external_id']) {
            $matches = Agency::query()->where('external_id', $incoming['external_id'])->limit(2)->get();
            if ($matches->count() === 1) {
                return [$matches->first(), null];
            }
            if ($matches->count() > 1) {
                return [null, 'external_id duplicado'];
            }
        }

        if ($incoming['code']) {
            $agency = Agency::query()->where('code', $incoming['code'])->first();
            if ($agency) {
                return [$agency, null];
            }
        }

        if ($incoming['department'] && $incoming['province'] && $incoming['district'] && $incoming['name']) {
            $needle = app(UpdateAgencyNameAction::class)->normalizeName($incoming['name']);
            $matches = Agency::query()
                ->where('department', $incoming['department'])
                ->where('province', $incoming['province'])
                ->where('district', $incoming['district'])
                ->get()
                ->filter(fn (Agency $agency) => app(UpdateAgencyNameAction::class)->normalizeName($agency->name) === $needle);

            if ($matches->count() === 1) {
                return [$matches->first(), null];
            }
            if ($matches->count() > 1) {
                return [null, 'coincidencia normalizada ambigua'];
            }
        }

        return [null, null];
    }

    private function classify(?Agency $agency, array $incoming, ?string $conflictReason, UpdateAgencyNameAction $nameAction): array
    {
        if ($conflictReason) {
            return ['conflict', [], null];
        }

        if (! $incoming['name']) {
            return ['invalid', ['name' => ['incoming' => null]], null];
        }

        if (! $agency) {
            return ['create', [], null];
        }

        $differences = [];
        foreach ($incoming as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $current = $agency->{$field};
            if ($field === 'name') {
                if ($nameAction->normalizeName((string) $current) !== $nameAction->normalizeName((string) $value)) {
                    $differences['name'] = ['current' => $current, 'incoming' => $value, 'proposed_old_name' => $current];
                }

                continue;
            }

            if ((string) $current !== (string) $value) {
                $differences[$field] = ['current' => $current, 'incoming' => $value];
            }
        }

        if (isset($differences['name'])) {
            return ['rename', $differences, $differences['name']['proposed_old_name']];
        }

        return $differences === [] ? ['unchanged', [], null] : ['update', $differences, null];
    }

    private function counterFor(string $action): string
    {
        return match ($action) {
            'create' => 'new_count',
            'update' => 'updated_count',
            'rename' => 'renamed_count',
            'conflict' => 'conflict_count',
            'invalid' => 'error_count',
            default => 'unchanged_count',
        };
    }

    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $value === '' ? null : $value;
    }
}
