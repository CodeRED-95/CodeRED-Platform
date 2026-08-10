<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Jobs\RestoreAgencyBackupJob;
use App\Modules\Agencies\Models\AgencyBackup;
use App\Modules\Agencies\Models\AgencyBackupRestore;
use App\Modules\Agencies\Services\AgencyBackupService;
use App\Modules\Agencies\Services\AgencyRestoreService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class Backups extends Component
{
    use WithFileUploads;

    public ?string $integrityResult = null;

    /** Archivo .json subido a mano para restaurar. */
    public $restoreFile;

    public string $restoreMode = AgencyBackupRestore::MODE_MERGE;

    /** Copia ya registrada que se quiere restaurar, si no se sube archivo. */
    public ?int $restoreBackupId = null;

    public ?int $activeRestoreId = null;

    public function mount(): void
    {
        Gate::authorize('agencies.backup.view');

        $this->activeRestoreId = AgencyBackupRestore::query()->latest('id')->value('id');
    }

    public function create(AgencyBackupService $service): void
    {
        Gate::authorize('agencies.backup.create');
        $backup = $service->create(auth()->id());
        $this->dispatch('toast', type: 'success', message: 'Copia creada: '.$backup->filename);
    }

    public function verifyIntegrity(int $backupId, AgencyBackupService $service): void
    {
        Gate::authorize('agencies.backup.view');
        $result = $service->verify(AgencyBackup::query()->findOrFail($backupId));
        $this->integrityResult = match ($result) {
            'integrity_ok' => 'Íntegro', 'altered' => 'Alterado', default => 'Archivo no encontrado',
        };
    }

    public function delete(int $backupId, AgencyBackupService $service): void
    {
        Gate::authorize('agencies.backup.delete');
        $service->delete(AgencyBackup::query()->findOrFail($backupId));
        $this->dispatch('toast', type: 'success', message: 'Copia eliminada.');
    }

    /**
     * Restaura desde un archivo subido a mano.
     */
    public function restoreFromUpload(AgencyRestoreService $service): void
    {
        Gate::authorize('agencies.backup.restore');

        $this->validate([
            'restoreFile' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:204800'],
            'restoreMode' => ['required', Rule::in(array_keys(AgencyBackupRestore::modes()))],
        ], [
            'restoreFile.required' => 'Selecciona el archivo .json de la copia.',
            'restoreFile.max' => 'El archivo supera el máximo de 200 MB.',
        ]);

        $disk = (string) config('agency_backups.disk', 'local');
        $directory = trim((string) config('agency_backups.directory', 'backups/agencies'), '/').'/restores';
        $filename = 'restore-'.now()->format('Y-m-d-His').'-'.Str::random(6).'.json';
        $path = $this->restoreFile->storeAs($directory, $filename, $disk);

        if ($path === false) {
            $this->addError('restoreFile', 'No se pudo guardar el archivo subido.');

            return;
        }

        // Se valida antes de encolar: así un archivo equivocado da un error
        // inmediato y comprensible en vez de un job fallido minutos después.
        try {
            $archive = $service->readArchive($disk, $path);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            $this->addError('restoreFile', $exception->getMessage());

            return;
        }

        $this->queueRestore([
            'filename' => $this->restoreFile->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'size_bytes' => (int) Storage::disk($disk)->size($path),
            'checksum_sha256' => hash_file('sha256', Storage::disk($disk)->path($path)) ?: null,
            'total_records' => count($archive['agencies']),
        ]);

        $this->reset('restoreFile');
    }

    /**
     * Restaura una copia ya registrada en la plataforma.
     */
    public function restoreFromBackup(int $backupId, AgencyRestoreService $service): void
    {
        Gate::authorize('agencies.backup.restore');

        $backup = AgencyBackup::query()->findOrFail($backupId);

        try {
            $archive = $service->readArchive($backup->disk, $backup->path);
        } catch (Throwable $exception) {
            $this->dispatch('toast', type: 'danger', message: $exception->getMessage());

            return;
        }

        $this->queueRestore([
            'agency_backup_id' => $backup->id,
            'filename' => $backup->filename,
            'disk' => $backup->disk,
            'path' => $backup->path,
            'size_bytes' => $backup->size_bytes,
            'checksum_sha256' => $backup->checksum_sha256,
            'total_records' => count($archive['agencies']),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function queueRestore(array $attributes): void
    {
        if (AgencyBackupRestore::query()->whereIn('status', ['pending', 'processing'])->exists()) {
            $this->dispatch('toast', type: 'warning', message: 'Ya hay una restauración en curso. Espera a que termine.');

            return;
        }

        $actorId = auth()->id();

        $restore = AgencyBackupRestore::query()->create($attributes + [
            'uuid' => (string) Str::uuid(),
            'mode' => $this->restoreMode,
            'status' => 'pending',
            'stage' => 'En cola',
            'created_by' => $actorId,
        ]);

        $this->activeRestoreId = $restore->id;

        // El actor viaja explícito en el job: el worker no tiene sesión y lo
        // necesita para sustituir FKs de usuario que no existan en el destino.
        RestoreAgencyBackupJob::dispatch($restore->id, $actorId)
            ->onConnection('redis')
            ->onQueue('agency-imports')
            ->afterCommit();

        $this->dispatch('toast', type: 'success', message: 'Restauración encolada. El progreso se actualiza en esta pantalla.');
    }

    public function render(): View
    {
        $activeRestore = $this->activeRestoreId !== null
            ? AgencyBackupRestore::query()->with(['createdBy', 'safetyBackup'])->find($this->activeRestoreId)
            : null;

        return view('livewire.admin.agencies.backups', [
            'backups' => AgencyBackup::query()->with('createdBy')->latest()->paginate(20),
            'activeRestore' => $activeRestore,
            'restores' => AgencyBackupRestore::query()->with('createdBy')->latest('id')->limit(10)->get(),
            'restoreModes' => AgencyBackupRestore::modes(),
            'canRestore' => auth()->user()?->hasPermission('agencies.backup.restore') ?? false,
        ])->layout('layouts.app', ['pageTitle' => 'Copias de seguridad de agencias']);
    }
}
