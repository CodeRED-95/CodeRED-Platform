<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Actions\ImportAgenciesAction;
use App\Modules\Agencies\Enums\AgencyImportStatus;
use App\Modules\Agencies\Enums\AgencyImportStrategy;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImport;
use App\Modules\Agencies\Models\AgencyImportFailure;
use App\Modules\Agencies\Services\AgencyDuplicateFinder;
use App\Modules\Agencies\Services\AgencyImportPreviewService;
use App\Modules\Agencies\Support\AgencyImportNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class Import extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public string $sourceType = 'url';

    public string $strategy = AgencyImportStrategy::IgnoreExisting->value;

    public string $statusOnCreate = 'under_review';

    public ?string $url = null;

    public ?string $jsonPayload = null;

    public ?TemporaryUploadedFile $file = null;

    public ?string $snapshotPath = null;

    public ?int $completedImportId = null;

    public array $preview = [];

    public array $summary = [];

    public array $failures = [];

    public array $payloadMetadata = [];

    public ?string $message = null;

    public function mount(): void
    {
        Gate::authorize('import', Agency::class);
    }

    public function goToValidation(): void
    {
        $this->validateSource();
        $this->resetValidationState();
        $this->step = 2;
    }

    public function validateAndPreview(AgencyImportPreviewService $service, AgencyDuplicateFinder $duplicateFinder): void
    {
        if ($this->step !== 2) {
            return;
        }

        try {
            $payload = $this->loadPayload($service);
            if (($this->payloadMetadata['format'] ?? null) === 'data.agencies') {
                $this->strategy = AgencyImportStrategy::UpdateExisting->value;
            }
            $this->analysePayload($payload, $duplicateFinder);
            $this->snapshotPath = $this->storeSnapshot($payload);
            $this->step = 3;
            $this->message = 'La fuente fue validada. Revisa los resultados antes de continuar.';
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('source', $this->friendlyError($exception));
        }
    }

    public function goToImport(): void
    {
        $this->validate([
            'strategy' => ['required', 'in:ignore_existing,update_existing,create_only_new,mark_conflicts'],
            'statusOnCreate' => ['required', 'in:under_review,active'],
            'snapshotPath' => ['required', 'string'],
        ]);

        if (! $this->snapshotExists()) {
            $this->addError('source', 'La vista previa expiró. Vuelve a validar la fuente.');

            return;
        }

        $this->step = 4;
    }

    public function import(ImportAgenciesAction $action): void
    {
        if ($this->step !== 4 || ! $this->snapshotExists()) {
            $this->addError('source', 'Debes validar y confirmar una vista previa antes de importar.');

            return;
        }

        if (($this->summary['invalid_rows'] ?? 0) > 0) {
            $this->addError('import', 'La restauración no puede ejecutarse porque contiene registros inválidos. Corrige las posiciones indicadas y vuelve a generar la vista previa.');

            return;
        }

        $payload = $this->readSnapshot();
        $import = AgencyImport::query()->create([
            'user_id' => auth()->id(),
            'original_filename' => $this->originalFilename(),
            'stored_filename' => $this->snapshotPath,
            'file_type' => $this->sourceType,
            'status' => AgencyImportStatus::Processing,
            'strategy' => $this->strategy,
            'total_rows' => count($payload),
            'started_at' => now(),
        ]);

        try {
            $result = $action->execute($import, $payload, $this->statusOnCreate);
            $status = $result['failed'] > 0
                ? AgencyImportStatus::CompletedWithErrors
                : AgencyImportStatus::Completed;
            $import->forceFill([
                'status' => $status,
                'valid_rows' => $result['imported'] + $result['updated'] + $result['skipped'],
                'imported_rows' => $result['imported'],
                'updated_rows' => $result['updated'],
                'skipped_rows' => $result['skipped'],
                'failed_rows' => $result['failed'],
                'completed_at' => now(),
            ])->save();

            $this->summary = [...$this->summary, ...$result];
            $this->failures = AgencyImportFailure::query()
                ->where('agency_import_id', $import->id)
                ->orderBy('row_number')
                ->limit(20)
                ->get()
                ->map(fn (AgencyImportFailure $failure): array => [
                    'row' => $failure->row_number,
                    'errors' => $failure->errors,
                ])->all();
            $this->completedImportId = $import->id;
            $this->step = 5;
            $this->message = $status === AgencyImportStatus::Completed
                ? 'Importación completada correctamente.'
                : 'Importación completada con incidencias.';
        } catch (Throwable $exception) {
            $import->forceFill([
                'status' => AgencyImportStatus::Failed,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();
            report($exception);
            $this->addError('import', 'La importación no pudo completarse. El intento quedó registrado.');
        }
    }

    public function backToSource(): void
    {
        $this->step = 1;
        $this->resetValidationState();
    }

    public function backToPreview(): void
    {
        if ($this->snapshotExists()) {
            $this->step = 3;
        }
    }

    public function resetWizard(): void
    {
        $this->reset([
            'url', 'jsonPayload', 'file', 'snapshotPath', 'completedImportId',
            'preview', 'summary', 'failures', 'message', 'payloadMetadata',
        ]);
        $this->step = 1;
        $this->sourceType = 'url';
        $this->strategy = AgencyImportStrategy::IgnoreExisting->value;
        $this->statusOnCreate = 'under_review';
        $this->resetErrorBag();
    }

    private function validateSource(): void
    {
        $this->validate([
            'sourceType' => ['required', 'in:json,url,file'],
            'url' => ['nullable', 'required_if:sourceType,url', 'url'],
            'jsonPayload' => ['nullable', 'required_if:sourceType,json', 'string'],
            'file' => ['nullable', 'required_if:sourceType,file', 'file', 'mimes:json', 'max:5120'],
        ], [
            'url.required_if' => 'Ingresa la URL de origen para continuar.',
            'jsonPayload.required_if' => 'Ingresa el contenido JSON para continuar.',
            'file.required_if' => 'Selecciona una copia de seguridad JSON para continuar.',
            'file.file' => 'El archivo seleccionado no es válido.',
            'file.mimes' => 'La copia de seguridad debe ser un archivo JSON.',
            'file.max' => 'La copia de seguridad supera el tamaño permitido.',
        ]);
    }

    private function loadPayload(AgencyImportPreviewService $service): array
    {
        $this->validateSource();
        $result = match ($this->sourceType) {
            'url' => $service->normalizePayload($service->payloadFromUrl((string) $this->url)),
            'file' => $service->payloadFromJson(file_get_contents((string) $this->file?->getRealPath())),
            default => $service->payloadFromJson((string) $this->jsonPayload),
        };

        $this->payloadMetadata = collect($result)->except('agencies')->all();
        $decoded = $result['agencies'];

        return array_map(
            fn (mixed $row): array => is_array($row) && array_is_list($row) === false
                ? $row
                : ['_invalid_row' => $row],
            $decoded,
        );
    }

    private function analysePayload(array $payload, AgencyDuplicateFinder $duplicateFinder): void
    {
        $preview = [];
        $summary = [
            'total_rows' => count($payload),
            'valid_rows' => 0,
            'warning_rows' => 0,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'legacy_classified' => 0,
            'legacy_unclassified' => 0,
            'identity_conflicts' => 0,
        ];

        $seenExternalIds = [];

        foreach ($payload as $index => $row) {
            $transformed = AgencyImportNormalizer::transform($row);
            $resolution = $transformed->valid ? $duplicateFinder->resolve($transformed->normalized) : ['agency' => null, 'conflict' => null];
            $duplicate = $resolution['agency'] !== null;
            $externalId = $transformed->normalized['external_id'] ?? null;
            $duplicateInFile = is_int($externalId) && isset($seenExternalIds[$externalId]);
            if (is_int($externalId)) {
                $seenExternalIds[$externalId] = true;
            }
            $identityConflict = $resolution['conflict'] !== null || $duplicateInFile;
            $summary['valid_rows'] += $transformed->valid ? 1 : 0;
            $summary['warning_rows'] += $transformed->warnings !== [] ? 1 : 0;
            $summary['invalid_rows'] += $transformed->valid ? 0 : 1;
            $summary['duplicate_rows'] += $duplicate ? 1 : 0;
            $summary['identity_conflicts'] += $identityConflict ? 1 : 0;
            $summary['legacy_classified'] += collect($transformed->warnings)->contains(fn (string $warning): bool => str_contains($warning, 'heredado clasificado')) ? 1 : 0;
            $summary['legacy_unclassified'] += collect($transformed->warnings)->contains(fn (string $warning): bool => str_contains($warning, 'no pudo clasificarse')) ? 1 : 0;

            if ($index < 20) {
                $preview[] = [
                    ...$transformed->toArray(),
                    'row_number' => $index + 1,
                    'duplicate' => $duplicate,
                    'identity_conflict' => $identityConflict,
                ];
            }
        }

        $this->preview = $preview;
        $this->summary = $summary;
    }

    private function storeSnapshot(array $payload): string
    {
        $path = 'imports/agencies/previews/'.Str::uuid().'.json';
        Storage::disk('local')->put($path, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function readSnapshot(): array
    {
        return json_decode(Storage::disk('local')->get((string) $this->snapshotPath), true, 512, JSON_THROW_ON_ERROR);
    }

    private function snapshotExists(): bool
    {
        return filled($this->snapshotPath) && Storage::disk('local')->exists((string) $this->snapshotPath);
    }

    private function originalFilename(): string
    {
        return $this->file?->getClientOriginalName()
            ?? ($this->url ? basename(parse_url($this->url, PHP_URL_PATH) ?: 'gist.json') : 'import.json');
    }

    private function resetValidationState(): void
    {
        if ($this->snapshotExists() && $this->completedImportId === null) {
            Storage::disk('local')->delete((string) $this->snapshotPath);
        }
        $this->reset(['snapshotPath', 'completedImportId', 'preview', 'summary', 'failures', 'message']);
        $this->resetErrorBag();
    }

    private function friendlyError(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof JsonException => 'El contenido no es un JSON válido.',
            $exception instanceof InvalidArgumentException => $exception->getMessage(),
            default => 'No fue posible validar la fuente. Revisa la URL o el archivo.',
        };
    }

    public function render(): View
    {
        return view('livewire.admin.agencies.import')->layout('layouts.app', ['pageTitle' => 'Importar agencias']);
    }
}
