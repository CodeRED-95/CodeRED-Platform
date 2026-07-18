<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Actions\ImportAgenciesAction;
use App\Modules\Agencies\Enums\AgencyImportStatus;
use App\Modules\Agencies\Enums\AgencyImportStrategy;
use App\Modules\Agencies\Models\AgencyImport;
use App\Modules\Agencies\Services\AgencyImportPreviewService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Import extends Component
{
    use WithFileUploads;

    public string $sourceType = 'json';
    public string $strategy = AgencyImportStrategy::IgnoreExisting->value;
    public string $statusOnCreate = 'under_review';
    public ?string $url = null;
    public ?string $jsonPayload = null;
    public ?TemporaryUploadedFile $file = null;
    public array $preview = [];
    public array $summary = [];
    public ?string $message = null;

    public function mount(): void
    {
        Gate::authorize('import', AgencyImport::class);
    }

    public function preview(AgencyImportPreviewService $service): void
    {
        $payload = $this->loadPayload($service);
        $preview = [];
        $valid = 0;
        $warnings = 0;
        $invalid = 0;

        foreach (array_slice($payload, 0, 20) as $row) {
            $transformed = \App\Modules\Agencies\Support\AgencyImportNormalizer::transform($row);
            $preview[] = $transformed->toArray();
            $valid += $transformed->valid ? 1 : 0;
            $warnings += count($transformed->warnings);
            $invalid += $transformed->valid ? 0 : 1;
        }

        $this->preview = $preview;
        $this->summary = [
            'total_rows' => count($payload),
            'valid_rows' => $valid,
            'warning_rows' => $warnings,
            'invalid_rows' => $invalid,
        ];
        $this->message = 'Vista previa generada correctamente.';
    }

    public function import(AgencyImportPreviewService $service, ImportAgenciesAction $action): void
    {
        $payload = $this->loadPayload($service);
        $import = AgencyImport::query()->create([
            'user_id' => auth()->id(),
            'original_filename' => $this->file?->getClientOriginalName() ?? ($this->url ? basename(parse_url($this->url, PHP_URL_PATH) ?: 'gist.json') : 'import.json'),
            'stored_filename' => $this->storePayload(),
            'file_type' => $this->sourceType,
            'status' => AgencyImportStatus::Processing,
            'strategy' => $this->strategy,
            'total_rows' => count($payload),
            'started_at' => now(),
        ]);

        $summary = $action->execute($import, $payload, $this->statusOnCreate);

        $import->forceFill([
            'status' => AgencyImportStatus::Completed,
            'valid_rows' => $summary['imported'] + $summary['updated'] + $summary['skipped'],
            'imported_rows' => $summary['imported'],
            'updated_rows' => $summary['updated'],
            'skipped_rows' => $summary['skipped'],
            'failed_rows' => $summary['failed'],
            'completed_at' => now(),
        ])->save();

        $this->message = 'Importación completada.';
        $this->redirectRoute('admin.agencies.index');
    }

    private function loadPayload(AgencyImportPreviewService $service): array
    {
        $this->validate([
            'sourceType' => ['required', 'in:json,url,file'],
            'url' => ['nullable', 'required_if:sourceType,url', 'url'],
            'jsonPayload' => ['nullable', 'required_if:sourceType,json', 'string'],
            'file' => ['nullable', 'required_if:sourceType,file', 'file', 'mimes:json', 'max:5120'],
        ]);

        return match ($this->sourceType) {
            'url' => $service->payloadFromUrl((string) $this->url),
            'file' => json_decode($this->file->getRealPath() ? file_get_contents($this->file->getRealPath()) : '[]', true, 512, JSON_THROW_ON_ERROR),
            default => json_decode((string) $this->jsonPayload, true, 512, JSON_THROW_ON_ERROR),
        };
    }

    private function storePayload(): string
    {
        if ($this->file instanceof UploadedFile) {
            return $this->file->storeAs('imports/agencies', Str::random(40).'.json');
        }

        return 'transient-'.Str::random(24).'.json';
    }

    public function render()
    {
        return view('livewire.admin.agencies.import')->layout('layouts.app');
    }
}
