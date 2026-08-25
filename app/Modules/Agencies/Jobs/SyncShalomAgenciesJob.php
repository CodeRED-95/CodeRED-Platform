<?php

namespace App\Modules\Agencies\Jobs;

use App\Modules\Agencies\Actions\UpdateAgencyNameAction;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportItem;
use App\Modules\Agencies\Models\AgencyImportRun;
use App\Modules\Agencies\Support\AgencySyncFields;
use App\Modules\Agencies\Services\ChosenFileParser;
use App\Services\Agencies\ShalomAgencyNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SyncShalomAgenciesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;

    /**
     * `$chosenPath` es opcional: sin archivo Chosen la sincronizacion se
     * ejecuta igual y los textos texto_chosen_* de cada agencia se dejan como
     * estan (ConfirmAgencyImportRunAction ignora los valores vacios).
     */
    public function __construct(public int $importRunId, public ?string $chosenPath = null) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('shalom-agencies-sync'))->expireAfter(600)];
    }

    public function handle(ChosenFileParser $chosenParser, ShalomAgencyNormalizer $normalizer, UpdateAgencyNameAction $nameAction): void
    {
        $run = AgencyImportRun::query()->findOrFail($this->importRunId);

        if (! config('services.shalom_extractor.enabled', true)) {
            throw new RuntimeException('El extractor Shalom está deshabilitado. Revisa SHALOM_EXTRACTOR_ENABLED.');
        }

        $run->update(['status' => 'running', 'stage' => 'Extrayendo agencias Shalom', 'started_at' => now(), 'progress' => 10]);

        try {
            $chosenFileContent = $this->chosenPath !== null ? Storage::get($this->chosenPath) : null;
            $chosenRows = $chosenFileContent !== null
                ? collect($chosenParser($chosenFileContent))->keyBy('external_id')
                : collect();

            $timeout = (int) config('services.shalom_extractor.timeout', 180);
            $url = rtrim((string) config('services.shalom_extractor.url', 'http://shalom-extractor:3000'), '/');

            // El extractor no consume el Chosen: saca las agencias de
            // shalom.com.pe por su cuenta. Solo se envia cuando existe, por
            // compatibilidad con versiones anteriores del servicio.
            $response = Http::connectTimeout(15)->timeout($timeout)->retry(2, 1500)->post(
                $url.'/extract',
                $chosenFileContent !== null ? ['chosenFileContent' => $chosenFileContent] : []
            );

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
            $normalizedRows = [];
            $baseRows = [];
            $counts = ['new_count' => 0, 'updated_count' => 0, 'renamed_count' => 0, 'unchanged_count' => 0, 'conflict_count' => 0, 'missing_count' => 0, 'error_count' => 0];
            $stats = ['total_extracted' => $incomingRows->count(), 'total_normalized' => 0, 'created' => 0, 'updated' => 0, 'ignored' => 0, 'conflicts' => 0, 'district_from_place' => 0, 'without_district' => 0, 'without_coordinates' => 0];

            $run->items()->delete();
            $run->update(['stage' => 'Preparando vista previa', 'progress' => 50, 'total_received' => $incomingRows->count()]);

            foreach ($incomingRows as $row) {
                $baseRow = $this->extractBaseRow($row);
                $normalized = $normalizer->normalize($row);
                $externalIdKey = $normalized['external_id'] ?? null;

                if ($chosenFileContent === null) {
                    // Sin archivo Chosen no hay fuente para estos textos. El
                    // normalizador los fabrica a partir de los datos de la
                    // agencia ("28 - CAJAMARCA - JAEN - JAEN - JAEN CO -
                    // TERRESTRE"), pero el valor real de Shalom no siempre
                    // tiene esa forma, asi que escribirlos sustituiria un dato
                    // bueno por uno inventado. El buscador compara ese texto
                    // letra a letra contra las opciones del selector, de modo
                    // que un valor fabricado deja de seleccionar la agencia.
                    // Se dejan en null: ni se comparan ni se escriben.
                    $normalized['texto_chosen_terrestre'] = null;
                    $normalized['texto_chosen_aereo'] = null;
                } elseif ($externalIdKey !== null && $chosenRows->has($externalIdKey)) {
                    $chosen = $chosenRows->get($externalIdKey);
                    $normalized['texto_chosen_terrestre'] = $chosen['texto_chosen_terrestre'] ?? $normalized['texto_chosen_terrestre'] ?? null;
                    $normalized['texto_chosen_aereo'] = $chosen['texto_chosen_aereo'] ?? $normalized['texto_chosen_aereo'] ?? null;
                }

                // A la escala real de la columna: si se compara con mas decimales
                // de los que la base puede guardar, la diferencia es perpetua.
                $normalized['latitude'] = AgencySyncFields::roundCoordinate($this->nullableFloat($normalized['latitude'] ?? null));
                $normalized['longitude'] = AgencySyncFields::roundCoordinate($this->nullableFloat($normalized['longitude'] ?? null));
                $normalized['place'] = $baseRow['place'] ?? $normalized['place'] ?? null;
                $normalized['map_url'] = $this->resolveMapUrl($normalized, $row);

                if (($normalized['district'] ?? null) === null && filled($normalized['place'] ?? null)) {
                    $stats['district_from_place']++;
                }
                if (($normalized['district'] ?? null) === null) {
                    $stats['without_district']++;
                }
                if (($normalized['latitude'] ?? null) === null || ($normalized['longitude'] ?? null) === null) {
                    $stats['without_coordinates']++;
                }

                $analysisAgency = $this->resolveAnalysisAgency($normalized);
                if (! $analysisAgency && empty(array_filter($normalized, static fn ($value) => $value !== null && $value !== ''))) {
                    throw new RuntimeException('No se encontró una agencia vinculada ni datos normalizados para reintentar el análisis.');
                }

                $stats['total_normalized']++;
                $baseRows[] = $baseRow;
                $normalizedRows[] = $normalized;

                [$agency, $conflictReason] = $this->matchAgency($normalized);
                [$action, $differences, $proposedOldName] = $this->classify($agency, $normalized, $conflictReason, $nameAction);
                $counts[$this->counterFor($action)]++;
                if ($action === 'create') {
                    $stats['created']++;
                } elseif ($action === 'update') {
                    $stats['updated']++;
                } elseif (in_array($action, ['conflict', 'invalid', 'unchanged', 'missing'], true)) {
                    $stats['ignored']++;
                }
                if ($action === 'conflict') {
                    $stats['conflicts']++;
                }

                AgencyImportItem::create([
                    'import_run_id' => $run->id,
                    'external_id' => $normalized['external_id'] ?? null,
                    'matched_agency_id' => $agency?->id,
                    'action' => $action,
                    'confidence' => $agency ? 100 : 0,
                    'incoming_data' => $normalized,
                    'current_data' => $agency?->only(array_keys($normalized)),
                    'differences' => $differences,
                    'proposed_old_name' => $proposedOldName,
                    'conflict_reason' => $conflictReason,
                    'selected' => ! in_array($action, ['conflict', 'unchanged', 'invalid'], true),
                ]);

                $processedRows[] = $this->toApiFormat($normalized, $agency, $baseRow);
            }

            Storage::put($basePath.'/base_agencias.json', json_encode($baseRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            Storage::put($basePath.'/clean_agencias.json', json_encode($normalizedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            Storage::put($basePath.'/formato_api.json', json_encode($processedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            Storage::put($basePath.'/report.json', json_encode(array_merge($counts, $stats), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $run->update(array_merge($counts, [
                'status' => 'ready_for_review',
                'stage' => 'Lista para revisión',
                'progress' => 100,
                'finished_at' => now(),
                'total_processed' => count($processedRows),
                'extracted_json_path' => $basePath.'/clean_agencias.json',
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

    private function extractBaseRow(array $row): array
    {
        return [
            'external_id' => $row['external_id'] ?? data_get($row, 'source_record.ter_id'),
            'code' => $row['code'] ?? data_get($row, 'source_record.ter_abrebiatura'),
            'name' => $row['name'] ?? data_get($row, 'source_record.lugar_over'),
            'place' => $row['place'] ?? data_get($row, 'source_record.nombre'),
            'zone' => $row['zone'] ?? data_get($row, 'source_record.zona'),
            'department' => $row['department'] ?? data_get($row, 'source_record.departamento'),
            'province' => $row['province'] ?? data_get($row, 'source_record.provincia'),
            'address' => $row['address'] ?? data_get($row, 'source_record.direccion'),
            'latitude' => $row['latitude'] ?? data_get($row, 'source_record.latitud'),
            'longitude' => $row['longitude'] ?? data_get($row, 'source_record.longitud'),
            'schedule' => $row['schedule'] ?? [
                'general' => data_get($row, 'source_record.hora_atencion'),
                'sunday' => data_get($row, 'source_record.hora_domingo'),
            ],
            'services' => $row['services'] ?? [],
            'classification' => $row['classification'] ?? [
                'category' => data_get($row, 'source_record.ter_categoria'),
                'sends_category' => data_get($row, 'source_record.ter_categoria_envia'),
                'receives_category' => data_get($row, 'source_record.ter_categoria_recibe'),
            ],
            'geographic_ids' => $row['geographic_ids'] ?? ['ubigeo_id' => data_get($row, 'source_record.ubi_id')],
            'status' => $row['status'] ?? data_get($row, 'source_record.status'),
            'source' => $row['source'] ?? data_get($row, 'source_record.source'),
            'source_record' => $row['source_record'] ?? [],
        ];
    }

    private function resolveAnalysisAgency(array $normalized): ?Agency
    {
        if (! empty($normalized['agency_id'])) {
            $agency = Agency::query()->find($normalized['agency_id']);
            if ($agency) {
                return $agency;
            }
        }

        if (filled($normalized['external_id'] ?? null)) {
            $agency = Agency::query()->where('external_id', $normalized['external_id'])->first();
            if ($agency) {
                return $agency;
            }
        }

        if (filled($normalized['code'] ?? null)) {
            return Agency::query()->where('code', $normalized['code'])->first();
        }

        return null;
    }

    private function matchAgency(array $incoming): array
    {
        $externalId = $incoming['external_id'] ?? null;

        if (! empty($externalId)) {
            $matches = Agency::query()->where('external_id', $externalId)->limit(2)->get();
            if ($matches->count() === 1) {
                return [$matches->first(), null];
            }
            if ($matches->count() > 1) {
                return [null, 'external_id duplicado'];
            }
        }

        if (! empty($incoming['code'])) {
            // Las candidatas que ya llevan otro external_id quedan fuera: no
            // son la misma agencia por compartir abreviatura. Si tras el
            // filtro no queda ninguna, es un alta; si queda mas de una, la
            // abreviatura no distingue y lo decide una persona.
            $candidates = Agency::query()
                ->where('code', $incoming['code'])
                ->get()
                ->reject(fn (Agency $agency) => $this->belongsToAnotherAgency($agency, $externalId));

            if ($candidates->count() === 1) {
                return [$candidates->first(), null];
            }

            if ($candidates->count() > 1) {
                return [null, sprintf('la abreviatura %s corresponde a %d agencias sin external_id', $incoming['code'], $candidates->count())];
            }
        }

        if ($incoming['department'] && $incoming['province'] && $incoming['district'] && $incoming['name']) {
            $needle = app(UpdateAgencyNameAction::class)->normalizeName($incoming['name']);
            $matches = Agency::query()
                ->where('department', $incoming['department'])
                ->where('province', $incoming['province'])
                ->where('district', $incoming['district'])
                ->get()
                ->filter(fn (Agency $agency) => app(UpdateAgencyNameAction::class)->normalizeName($agency->name) === $needle)
                ->reject(fn (Agency $agency) => $this->belongsToAnotherAgency($agency, $externalId));

            if ($matches->count() === 1) {
                return [$matches->first(), null];
            }
            if ($matches->count() > 1) {
                return [null, 'coincidencia normalizada ambigua'];
            }
        }

        return [null, null];
    }

    /**
     * Protege los emparejamientos de respaldo.
     *
     * `external_id` es la identidad autoritativa de Shalom. Si el entrante trae
     * uno y la agencia candidata ya lleva otro distinto, no son la misma
     * agencia por mucho que compartan abreviatura o nombre normalizado, y
     * emparejarlas significa proponer sobrescribir una ficha con los datos de
     * otra.
     *
     * Paso de verdad: SAN CLEMENTE (Pisco, Ica, external_id 496) entraba como
     * agencia nueva; al no existir aun ese external_id se caia al respaldo por
     * `code` y encontraba PISAC (Calca, Cusco), porque Shalom abrevia las dos
     * como PSC. La vista previa proponia convertir la agencia de Cusco en la de
     * Ica: mismo id interno, todos los datos cambiados.
     */
    private function belongsToAnotherAgency(Agency $agency, mixed $incomingExternalId): bool
    {
        if (blank($incomingExternalId) || blank($agency->external_id)) {
            return false;
        }

        return (string) $agency->external_id !== (string) $incomingExternalId;
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
        // Solo los campos que la confirmacion escribe de verdad: comparar el
        // resto proponia cambios que ninguna importacion podia aplicar.
        foreach (AgencySyncFields::FIELDS as $field) {
            $value = $incoming[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $current = $agency->{$field};
            if ($field === 'name') {
                $current = app(UpdateAgencyNameAction::class)->normalizeName((string) $current);
                $value = $nameAction->normalizeName((string) $value);
            }

            if (! AgencySyncFields::matches($field, $current, $value)) {
                $differences[$field] = ['current' => $current, 'incoming' => $value];
            }
        }

        if ($differences === []) {
            return ['unchanged', [], null];
        }

        $proposedOldName = null;
        if (array_key_exists('name', $differences)) {
            $proposedOldName = $agency->name;
            $differences['old_name'] = ['current' => $agency->old_name, 'incoming' => $proposedOldName];
        }

        return ['update', $differences, $proposedOldName];
    }

    private function counterFor(string $action): string
    {
        return match ($action) {
            'create' => 'new_count',
            'update', 'rename' => 'updated_count',
            'conflict' => 'conflict_count',
            'missing' => 'missing_count',
            'invalid' => 'error_count',
            default => 'unchanged_count',
        };
    }

    private function resolveMapUrl(array $normalized, array $row): ?string
    {
        if (filled($normalized['latitude'] ?? null) && filled($normalized['longitude'] ?? null)) {
            return 'https://www.google.com/maps/dir/?api=1&destination='.(string) $normalized['latitude'].','.(string) $normalized['longitude'];
        }

        return $row['map_url'] ?? data_get($row, 'source_record.link_mapa') ?? null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || strtolower($value) === 'null') {
                return null;
            }
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function toApiFormat(array $normalized, ?Agency $agency, array $rawData = []): array
    {
        $agencyData = [
            'external_id' => data_get($agency, 'external_id')
                ?? data_get($normalized, 'external_id')
                ?? data_get($rawData, 'external_id')
                ?? data_get($rawData, 'source_record.ter_id'),
            'internal_id' => $agency?->id,
            'code' => data_get($agency, 'code')
                ?? data_get($normalized, 'code')
                ?? data_get($rawData, 'code')
                ?? data_get($rawData, 'source_record.ter_abrebiatura'),
            'name' => data_get($agency, 'name')
                ?? data_get($normalized, 'name')
                ?? data_get($rawData, 'name')
                ?? data_get($rawData, 'source_record.lugar_over'),
            'old_name' => data_get($agency, 'old_name'),
            'place' => data_get($agency, 'place')
                ?? data_get($normalized, 'place')
                ?? data_get($rawData, 'place')
                ?? data_get($rawData, 'source_record.nombre'),
            'department' => data_get($agency, 'department')
                ?? data_get($normalized, 'department')
                ?? data_get($rawData, 'department')
                ?? data_get($rawData, 'source_record.departamento'),
            'province' => data_get($agency, 'province')
                ?? data_get($normalized, 'province')
                ?? data_get($rawData, 'province')
                ?? data_get($rawData, 'source_record.provincia'),
            'district' => data_get($agency, 'district')
                ?? data_get($normalized, 'district')
                ?? data_get($rawData, 'district')
                ?? data_get($rawData, 'zone')
                ?? data_get($rawData, 'source_record.zona'),
            'address' => data_get($agency, 'address')
                ?? data_get($normalized, 'address')
                ?? data_get($rawData, 'address')
                ?? data_get($rawData, 'source_record.direccion'),
            'latitude' => data_get($agency, 'latitude')
                ?? data_get($normalized, 'latitude')
                ?? data_get($rawData, 'latitude')
                ?? data_get($rawData, 'source_record.latitud'),
            'longitude' => data_get($agency, 'longitude')
                ?? data_get($normalized, 'longitude')
                ?? data_get($rawData, 'longitude')
                ?? data_get($rawData, 'source_record.longitud'),
            'map_url' => data_get($agency, 'map_url')
                ?? data_get($normalized, 'map_url')
                ?? data_get($rawData, 'map_url'),
            'schedule' => [
                'general' => data_get($agency, 'schedule_general')
                    ?? data_get($normalized, 'schedule.general')
                    ?? data_get($rawData, 'schedule.general')
                    ?? data_get($rawData, 'source_record.hora_atencion'),
                'sunday' => data_get($agency, 'schedule_sunday')
                    ?? data_get($normalized, 'schedule.sunday')
                    ?? data_get($rawData, 'schedule.sunday')
                    ?? data_get($rawData, 'source_record.hora_domingo'),
            ],
            'classification' => [
                'category' => data_get($agency, 'classification_category')
                    ?? data_get($normalized, 'classification.category')
                    ?? data_get($normalized, 'classification.tamano')
                    ?? data_get($normalized, 'tamano')
                    ?? data_get($rawData, 'classification.category')
                    ?? data_get($rawData, 'classification.tamano')
                    ?? data_get($rawData, 'source_record.ter_categoria'),
                'sends_category' => data_get($agency, 'classification_sends_category')
                    ?? data_get($normalized, 'classification.sends_category')
                    ?? data_get($normalized, 'sends_category')
                    ?? data_get($rawData, 'classification.sends_category')
                    ?? data_get($rawData, 'source_record.ter_categoria_envia'),
                'receives_category' => data_get($agency, 'classification_receives_category')
                    ?? data_get($normalized, 'classification.receives_category')
                    ?? data_get($normalized, 'receives_category')
                    ?? data_get($rawData, 'classification.receives_category')
                    ?? data_get($rawData, 'source_record.ter_categoria_recibe'),
            ],
            'chosen_terrestre' => data_get($agency, 'texto_chosen_terrestre')
                ?? data_get($normalized, 'chosen_terrestre')
                ?? data_get($normalized, 'texto_chosen_terrestre')
                ?? data_get($rawData, 'chosen_terrestre'),
            'chosen_aereo' => data_get($agency, 'texto_chosen_aereo')
                ?? data_get($normalized, 'chosen_aereo')
                ?? data_get($normalized, 'texto_chosen_aereo')
                ?? data_get($rawData, 'chosen_aereo'),
            'status' => data_get($agency, 'status.value'),
            'estado' => data_get($agency, 'status')?->label(),
            'centro_operaciones' => (bool) (data_get($agency, 'is_operations_center') ?? data_get($normalized, 'is_operations_center', false)),
        ];

        return [
            'external_id' => $agencyData['external_id'],
            'internal_id' => $agencyData['internal_id'],
            'code' => $agencyData['code'],
            'name' => $agencyData['name'],
            'old_name' => $agencyData['old_name'],
            'place' => $agencyData['place'],
            'department' => $agencyData['department'],
            'province' => $agencyData['province'],
            'district' => $agencyData['district'],
            'address' => $agencyData['address'],
            'latitude' => $agencyData['latitude'] !== null ? (float) $agencyData['latitude'] : null,
            'longitude' => $agencyData['longitude'] !== null ? (float) $agencyData['longitude'] : null,
            'map_url' => $agencyData['map_url'],
            'schedule' => $agencyData['schedule'],
            'classification' => $agencyData['classification'],
            'chosen_terrestre' => $agencyData['chosen_terrestre'],
            'chosen_aereo' => $agencyData['chosen_aereo'],
            'status' => $agencyData['status'],
            'estado' => $agencyData['estado'],
            'centro_operaciones' => $agencyData['centro_operaciones'],
        ];
    }
}
