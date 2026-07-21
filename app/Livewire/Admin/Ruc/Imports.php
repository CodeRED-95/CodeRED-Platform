<?php

namespace App\Livewire\Admin\Ruc;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Services\RucImportService;
use App\Modules\Ruc\Support\EncodingNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

class Imports extends Component
{
    use WithFileUploads;

    public $file;

    public bool $force = false;

    public function mount(): void
    {
        Gate::authorize('ruc.import-history');
    }

    public function start(RucImportService $service): void
    {
        Gate::authorize('ruc.import');
        Log::info('Solicitud de importación RUC recibida', ['user_id' => auth()->id(), 'has_file' => $this->file !== null]);
        try {
            EncodingNormalizer::normalize((string) config('ruc.import_encoding'));
        } catch (InvalidArgumentException) {
            $this->addError('file', 'No se pudo iniciar la importación porque la codificación configurada no es compatible. Revisa RUC_IMPORT_ENCODING.');

            return;
        }
        $this->validate(['file' => ['required', 'file', 'extensions:txt', 'max:'.(max(1, (int) config('ruc.import_max_size_mb')) * 1024)]], ['file.required' => 'Selecciona un archivo TXT.', 'file.file' => 'El archivo seleccionado no es válido.', 'file.extensions' => 'El padrón debe ser un archivo TXT.', 'file.max' => 'El archivo supera el tamaño permitido.']);
        $import = $service->fromUpload($this->file, (int) auth()->id(), $this->force);
        $this->reset(['file', 'force']);
        $this->dispatch('toast', type: 'success', message: 'Importación encolada: '.$import->uuid);
    }

    public function checkConfiguration(): void
    {
        Gate::authorize('ruc.import');

        try {
            EncodingNormalizer::normalize((string) config('ruc.import_encoding'));
            $disk = Storage::disk((string) config('ruc.import_disk'));
            $directory = trim((string) config('ruc.import_directory'), '/');
            if (! $disk->makeDirectory($directory) || ! $disk->exists($directory)) {
                throw new InvalidArgumentException('El directorio privado de importación no está disponible.');
            }
            Redis::connection()->ping();
            if (blank(config('ruc.import_queue'))) {
                throw new InvalidArgumentException('La cola RUC no está configurada.');
            }

            $this->dispatch('toast', type: 'success', message: 'Configuración RUC comprobada: codificación, almacenamiento, Redis y cola disponibles.');
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', 'La configuración RUC no está lista: '.$exception->getMessage());
        }
    }

    public function cancel(int $id): void
    {
        Gate::authorize('ruc.cancel-import');
        $import = RucImport::query()->findOrFail($id);
        abort_unless($import->status->active(), 422);
        $changes = ['cancel_requested_at' => now(), 'last_message' => 'Cancelación solicitada; esperando confirmación del worker.'];
        if (in_array($import->status, [RucImportStatus::Pending, RucImportStatus::Queued], true)) {
            $changes += ['status' => RucImportStatus::Cancelled, 'finished_at' => now(), 'last_heartbeat_at' => now()];
        }
        $import->update($changes);
        $this->dispatch('toast', type: 'success', message: 'La cancelación fue solicitada.');
    }

    public function retry(int $id): void
    {
        Gate::authorize('ruc.import');
        $import = RucImport::query()->findOrFail($id);
        abort_unless(in_array($import->status, [RucImportStatus::Failed, RucImportStatus::Cancelled], true), 422);
        abort_unless(Storage::disk($import->disk)->exists($import->path), 422, 'El archivo fuente ya no existe.');
        $import->errors()->delete();
        $import->update([
            'status' => RucImportStatus::Queued,
            'processed_rows' => 0,
            'inserted_rows' => 0,
            'ignored_rows' => 0,
            'invalid_rows' => 0,
            'progress_percentage' => 0,
            'current_chunk' => 0,
            'started_at' => null,
            'finished_at' => null,
            'failed_at' => null,
            'last_heartbeat_at' => null,
            'cancel_requested_at' => null,
            'error_message' => null,
            'last_message' => 'Reintento en cola; esperando al worker.',
        ]);
        ProcessRucImportJob::dispatch($import->id)->onConnection('redis')->onQueue($import->queue_name);
        $this->dispatch('toast', type: 'success', message: 'Importación reenviada a la cola.');
    }

    public function markStalledFailed(int $id): void
    {
        Gate::authorize('ruc.cancel-import');
        $import = RucImport::query()->findOrFail($id);
        abort_unless($this->isStalled($import), 422);
        $import->update([
            'status' => RucImportStatus::Failed,
            'cancel_requested_at' => now(),
            'failed_at' => now(),
            'error_message' => 'La importación fue marcada como detenida porque no registró actividad.',
            'last_message' => 'Marcada como fallida por falta de actividad.',
        ]);
    }

    public function deleteFile(int $id): void
    {
        Gate::authorize('ruc.delete-import-file');
        $import = RucImport::query()->findOrFail($id);
        abort_if($import->status->active(), 422);
        Storage::disk($import->disk)->delete($import->path);
        $import->update(['path' => 'deleted']);
        $this->dispatch('toast', type: 'success', message: 'Archivo fuente eliminado; el historial fue conservado.');
    }

    public function render(): View
    {
        $active = RucImport::query()->whereIn('status', [RucImportStatus::Pending, RucImportStatus::Queued, RucImportStatus::Validating, RucImportStatus::Processing])->latest()->first();
        $elapsed = $active?->started_at?->diffInSeconds(now()) ?? 0;
        $speed = $elapsed > 0 ? round($active->processed_rows / $elapsed, 1) : 0;
        $remaining = $speed > 0 && $active?->total_rows > 0 ? max(0, (int) round(($active->total_rows - $active->processed_rows) / $speed)) : null;

        $configuration = $this->configurationStatus();

        return view('livewire.admin.ruc.imports', ['activeImport' => $active, 'speed' => $speed, 'remainingSeconds' => $remaining, 'isStalled' => $active !== null && $this->isStalled($active), 'configuration' => $configuration, 'imports' => RucImport::query()->with('createdBy')->latest()->paginate(20)])
            ->layout('layouts.app', ['pageTitle' => 'Importaciones RUC']);
    }

    private function configurationStatus(): array
    {
        try {
            $encoding = EncodingNormalizer::normalize((string) config('ruc.import_encoding'));

            return ['valid' => true, 'encoding' => $encoding, 'delimiter' => (string) config('ruc.import_delimiter'), 'queue' => (string) config('ruc.import_queue'), 'message' => 'Configuración lista.'];
        } catch (InvalidArgumentException) {
            return ['valid' => false, 'encoding' => (string) config('ruc.import_encoding'), 'delimiter' => (string) config('ruc.import_delimiter'), 'queue' => (string) config('ruc.import_queue'), 'message' => 'La codificación configurada no es compatible. Revisa RUC_IMPORT_ENCODING.'];
        }
    }

    private function isStalled(RucImport $import): bool
    {
        $reference = $import->last_heartbeat_at ?? $import->created_at;

        return $import->status->active()
            && $reference !== null
            && $reference->lt(now()->subSeconds(max(60, (int) config('ruc.stalled_after_seconds'))));
    }
}
