<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Jobs\RestoreRucBackupJob;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backup/restore de ruc_records. Deliberadamente SIN Livewire, SIN fetch y
 * SIN JavaScript interceptando submits: formularios HTML tradicionales,
 * POST/DELETE normales, redirect + flash. Ver docs-ruc/BACKUP_SYSTEM.md.
 */
class RucBackupController
{
    public function index(): View
    {
        Gate::authorize('ruc.backup.view');

        $backups = RucBackup::query()->latest('id')->paginate(15);

        // NO ejecutar COUNT(*) sobre 18M filas en cada page load.
        // Usar cache invalidado solo después de import/restore.
        // Estrategia: RucStatistics tabla + cache con TTL 24h.
        // Ver docs-ruc/PERFORMANCE.md y fase 1 del plan de optimización.
        $currentRecordCount = Cache::remember('ruc:records:count', 86400, function () {
            return DB::table('ruc_statistics')->first()?->total_records ?? 0;
        });

        $lastBackup = RucBackup::query()->where('status', RucBackup::STATUS_COMPLETED)->latest('id')->first();
        // Solo la operación activa (pending/running): así, si alguien
        // recarga la página mientras un restore sigue corriendo en segundo
        // plano, la UI retoma el polling de inmediato en vez de volver al
        // estado "idle".
        $activeRestoreOperation = RucBackupOperation::activeRestore();

        // layouts.app (resources/views/layouts/app.blade.php) espera un
        // $slot, igual que lo consume Livewire para las páginas completas
        // de este panel (config/livewire.php: 'layout' => 'layouts.app').
        // OJO: no usar <x-layouts.app> aquí — ese tag de componente resuelve
        // a resources/views/components/layouts/app.blade.php (un stub
        // "{{ $slot }}" sin sidebar/toast/head, un archivo DISTINTO que
        // eclipsa al layout real y deja la página sin su chrome visual).
        return view('layouts.app', [
            'slot' => new HtmlString(view('ruc.admin.backups.index', [
                'backups' => $backups,
                'currentRecordCount' => $currentRecordCount,
                'lastBackup' => $lastBackup,
                'activeRestoreOperation' => $activeRestoreOperation,
            ])->render()),
        ]);
    }

    public function store(): RedirectResponse
    {
        Gate::authorize('ruc.backup.create');

        try {
            $backup = app(RucBackupService::class)->create(auth()->user());

            return redirect()->route('admin.ruc.backups')->with(
                'success',
                "Backup creado correctamente: {$backup->name} ({$backup->formattedSize()}, ".number_format($backup->total_records ?? 0).' registros).'
            );
        } catch (\Throwable $e) {
            Log::error('RUC backup creation failed', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return redirect()->route('admin.ruc.backups')->with('error', 'No se pudo crear el backup. Revisa storage/logs/laravel.log para más detalles.');
        }
    }

    public function download(RucBackup $backup): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('ruc.backup.download');

        if (! $backup->isCompleted() || ! $backup->fileExists()) {
            return redirect()->route('admin.ruc.backups')->with('error', 'El archivo de backup no está disponible.');
        }

        return response()->download($backup->absolutePath(), $backup->name);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('ruc.backup.create');

        $maxKb = config('ruc.backup.max_upload_mb') * 1024;

        $validated = $request->validate([
            'backup' => 'required|file|extensions:'.implode(',', RucBackup::ALLOWED_IMPORT_EXTENSIONS)."|max:{$maxKb}",
        ], [
            'backup.required' => 'Debes seleccionar un archivo.',
            'backup.extensions' => 'El archivo debe tener extensión .dump (o .gz para backups antiguos del sistema).',
            'backup.max' => 'El archivo excede el tamaño máximo permitido ('.config('ruc.backup.max_upload_mb').' MB).',
        ]);

        try {
            $file = $validated['backup'];
            app(RucBackupService::class)->import($file->getRealPath(), $file->getClientOriginalName(), auth()->user());

            return redirect()->route('admin.ruc.backups')->with('success', 'Backup importado correctamente.');
        } catch (\Throwable $e) {
            Log::warning('RUC backup import rejected', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return redirect()->route('admin.ruc.backups')->with('error', 'El archivo no es un backup RUC válido: '.$e->getMessage());
        }
    }

    /**
     * SOLO valida, crea la operación y despacha el Job — nunca ejecuta
     * pg_dump/psql aquí. Restaurar 18M+ registros dentro del request HTTP
     * es lo que causaba el 524 de Cloudflare (Cloudflare/nginx cortan la
     * conexión mucho antes de que un restore de esa magnitud termine); esta
     * respuesta debe volver en <2s siempre, sin importar cuánto tarde el
     * restore real. Ver RestoreRucBackupJob.
     */
    public function restore(RucBackup $backup): RedirectResponse
    {
        Gate::authorize('ruc.backup.restore');

        if (! $backup->isCompleted()) {
            return redirect()->route('admin.ruc.backups')->with('error', 'El backup debe estar completado para restaurar.');
        }

        if (RucBackupOperation::hasActiveRestore()) {
            return redirect()->route('admin.ruc.backups')->with('error', 'Ya hay una restauración de RUC en curso. Espera a que termine antes de iniciar otra.');
        }

        $operation = RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'progress' => 0,
            'message' => 'En cola',
            'created_by' => auth()->id(),
        ]);

        RestoreRucBackupJob::dispatch($operation->id);

        Log::info('RUC restore queued', ['operation_id' => $operation->id, 'operation_uuid' => $operation->uuid, 'backup_id' => $backup->id, 'user_id' => auth()->id()]);

        return redirect()->route('admin.ruc.backups')->with('success', 'Restauración iniciada.');
    }

    /** Consultado por polling desde la UI cada 2-3s mientras hay una operación activa. */
    public function operationStatus(RucBackupOperation $operation): JsonResponse
    {
        Gate::authorize('ruc.backup.view');

        return response()->json([
            'uuid' => $operation->uuid,
            'status' => $operation->status,
            'stage' => $operation->stage,
            'progress' => $operation->progress,
            'message' => $operation->message,
            'backup_name' => $operation->backup?->name,
            'safety_backup_id' => $operation->safety_backup_id,
            'records_before' => $operation->records_before,
            'records_after' => $operation->records_after,
            'started_at' => $operation->started_at?->toIso8601String(),
            'finished_at' => $operation->finished_at?->toIso8601String(),
            'duration_seconds' => $operation->duration_seconds,
            'error_message' => $operation->error_message,
        ]);
    }

    public function destroy(RucBackup $backup): RedirectResponse
    {
        Gate::authorize('ruc.backup.delete');

        $usedByActiveOperation = RucBackupOperation::query()
            ->whereIn('status', [RucBackupOperation::STATUS_PENDING, RucBackupOperation::STATUS_RUNNING])
            ->where(fn ($query) => $query->where('backup_id', $backup->id)->orWhere('safety_backup_id', $backup->id))
            ->exists();

        if ($usedByActiveOperation) {
            return redirect()->route('admin.ruc.backups')->with('error', 'No se puede eliminar: este backup está siendo usado por una restauración en curso.');
        }

        if ($backup->fileExists()) {
            @unlink($backup->absolutePath());
        }

        $backup->delete();

        Log::info('RUC backup deleted', ['backup_id' => $backup->id, 'user_id' => auth()->id()]);

        return redirect()->route('admin.ruc.backups')->with('success', 'Backup eliminado correctamente.');
    }
}
