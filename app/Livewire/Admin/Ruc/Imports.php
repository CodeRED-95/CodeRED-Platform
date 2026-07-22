<?php

namespace App\Livewire\Admin\Ruc;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Services\RucImportService;
use App\Modules\Ruc\Services\RucIncomingFileScanner;
use App\Modules\Ruc\Services\RucIncomingFileValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class Imports extends Component
{
    public array $availableFiles = [];

    public array $diagnostics = [];

    public array $fileValidation = [];

    public function mount(RucIncomingFileScanner $scanner): void
    {
        Gate::authorize('ruc.import-history');
        $this->refreshFiles($scanner);
    }

    public function scanFiles(RucIncomingFileScanner $scanner): void
    {
        Gate::authorize('ruc.import-history');
        $this->refreshFiles($scanner);
        $this->dispatch('toast', type: 'success', message: count($this->availableFiles).' archivos TXT detectados.');
    }

    public function validateIncomingFile(string $path, RucIncomingFileValidator $validator): void
    {
        Gate::authorize('ruc.import');
        $this->resetErrorBag('incomingFiles');
        Log::info('Validación manual de archivo RUC iniciada', ['user_id' => auth()->id(), 'path' => $path]);
        try {
            $result = $validator->validate($path);
            $this->fileValidation[md5($path)] = $result;
            if (! $result['valid']) {
                $this->addError('incomingFiles', $result['message']);
            }
            Log::info('Validación manual de archivo RUC finalizada', ['user_id' => auth()->id(), 'path' => $path, 'valid' => $result['valid']]);
        } catch (ValidationException $exception) {
            $this->addError('incomingFiles', $exception->validator->errors()->first());
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Falló la acción del archivo RUC', ['path' => $path, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
            $this->addError('incomingFiles', 'No fue posible validar el archivo. Revisa los registros.');
        }
    }

    public function registerIncomingFile(string $path, RucImportService $service, RucIncomingFileScanner $scanner): void
    {
        Gate::authorize('ruc.import');
        $this->resetErrorBag('incomingFiles');
        try {
            $import = $service->registerServerFile($path, (int) auth()->id());
            $this->refreshFiles($scanner);
            session()->flash('success', 'El archivo RUC fue registrado correctamente.');
            $this->dispatch('toast', type: 'success', message: 'El archivo RUC fue registrado correctamente.');
            Log::info('Registro manual de archivo RUC finalizado', ['import_id' => $import->id, 'path' => $path]);
        } catch (ValidationException $exception) {
            $this->addError('incomingFiles', $exception->validator->errors()->first());
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Falló la acción del archivo RUC', ['path' => $path, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
            $this->addError('incomingFiles', 'No fue posible registrar el archivo. Revisa los registros.');
        }
    }

    public function startImport(int $id, RucImportService $service, RucIncomingFileScanner $scanner): void
    {
        Gate::authorize('ruc.import');
        $service->startRegistered(RucImport::query()->findOrFail($id));
        $this->refreshFiles($scanner);
        $this->dispatch('toast', type: 'success', message: 'Importación enviada a ruc-imports.');
    }

    public function pause(int $id): void
    {
        Gate::authorize('ruc.cancel-import');
        $import = RucImport::query()->findOrFail($id);
        abort_unless($import->status === RucImportStatus::Processing, 422);
        $import->update(['status' => RucImportStatus::Paused, 'last_message' => 'Pausa solicitada; se detendrá después del lote actual.']);
    }

    public function resume(int $id): void
    {
        Gate::authorize('ruc.import');
        $import = RucImport::query()->findOrFail($id);
        abort_unless(in_array($import->status, [RucImportStatus::Paused, RucImportStatus::Failed], true), 422);
        $import->update(['status' => RucImportStatus::Queued, 'failed_at' => null, 'error_message' => null, 'last_message' => 'Reanudación enviada al worker.']);
        ProcessRucImportJob::dispatch($id)->onConnection('redis')->onQueue((string) config('ruc.import.queue'));
    }

    public function cancel(int $id): void
    {
        Gate::authorize('ruc.cancel-import');
        $import = RucImport::query()->findOrFail($id);
        abort_unless($import->status->active() || $import->status === RucImportStatus::Paused, 422);
        if (in_array($import->status, [RucImportStatus::Registered, RucImportStatus::Queued, RucImportStatus::Paused], true)) {
            $import->update(['status' => RucImportStatus::Cancelled, 'cancel_requested_at' => now(), 'finished_at' => now(), 'last_heartbeat_at' => now()]);

            return;
        }
        $import->update(['cancel_requested_at' => now(), 'last_message' => 'Cancelación solicitada; esperando el checkpoint actual.']);
    }

    public function retry(int $id): void
    {
        Gate::authorize('ruc.import');
        $import = RucImport::query()->findOrFail($id);
        abort_unless(in_array($import->status, [RucImportStatus::Failed, RucImportStatus::Cancelled], true), 422);
        DB::table('ruc_staging')->where('import_id', $id)->delete();
        $import->errors()->delete();
        $import->update(['status' => RucImportStatus::Queued, 'processed_rows' => 0, 'inserted_rows' => 0, 'ignored_rows' => 0, 'invalid_rows' => 0, 'resolved_ubigeo_rows' => 0, 'unknown_ubigeo_rows' => 0, 'address_rows' => 0, 'progress_percentage' => 0, 'current_chunk' => 0, 'current_byte_offset' => 0, 'current_line_number' => 0, 'last_completed_chunk' => 0, 'started_at' => null, 'finished_at' => null, 'failed_at' => null, 'last_heartbeat_at' => null, 'cancel_requested_at' => null, 'error_message' => null]);
        ProcessRucImportJob::dispatch($id)->onConnection('redis')->onQueue((string) config('ruc.import.queue'));
    }

    public function render(): View
    {
        $active = RucImport::query()->whereIn('status', [RucImportStatus::Queued, RucImportStatus::Validating, RucImportStatus::Processing, RucImportStatus::Paused])->latest()->first();
        $elapsed = $active?->started_at?->diffInSeconds(now()) ?? 0;
        $speed = $elapsed > 0 ? round($active->processed_rows / $elapsed, 1) : 0;
        $remaining = $speed > 0 && $active?->total_rows > 0 ? max(0, (int) (($active->total_rows - $active->processed_rows) / $speed)) : null;

        return view('livewire.admin.ruc.imports', ['activeImport' => $active, 'speed' => $speed, 'remainingSeconds' => $remaining, 'imports' => RucImport::query()->with('createdBy')->latest()->paginate(20)])
            ->layout('layouts.app', ['pageTitle' => 'Importaciones RUC']);
    }

    private function refreshFiles(RucIncomingFileScanner $scanner): void
    {
        $this->availableFiles = $scanner->scan();
        $this->diagnostics = $scanner->diagnostics();
    }
}
