<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
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

        // layouts.app (resources/views/layouts/app.blade.php) espera un
        // $slot, igual que lo consume Livewire para las páginas completas
        // de este panel (config/livewire.php: 'layout' => 'layouts.app').
        // OJO: no usar <x-layouts.app> aquí — ese tag de componente resuelve
        // a resources/views/components/layouts/app.blade.php (un stub
        // "{{ $slot }}" sin sidebar/toast/head, un archivo DISTINTO que
        // eclipsa al layout real y deja la página sin su chrome visual).
        return view('layouts.app', [
            'slot' => new HtmlString(view('ruc.admin.backups.index', ['backups' => $backups])->render()),
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

    public function restore(RucBackup $backup): RedirectResponse
    {
        Gate::authorize('ruc.backup.restore');

        try {
            $result = app(RucBackupService::class)->restore($backup, auth()->user());

            return redirect()->route('admin.ruc.backups')->with(
                'success',
                "RUC restaurados correctamente: {$result['records_restored']} registros en {$result['duration_seconds']}s. ".
                "Se creó un backup de seguridad (#{$result['safety_backup_id']}) antes de restaurar."
            );
        } catch (\Throwable $e) {
            Log::error('RUC restore failed', ['backup_id' => $backup->id, 'error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return redirect()->route('admin.ruc.backups')->with('error', 'Restore cancelado: '.$e->getMessage());
        }
    }

    public function destroy(RucBackup $backup): RedirectResponse
    {
        Gate::authorize('ruc.backup.delete');

        if ($backup->fileExists()) {
            @unlink($backup->absolutePath());
        }

        $backup->delete();

        Log::info('RUC backup deleted', ['backup_id' => $backup->id, 'user_id' => auth()->id()]);

        return redirect()->route('admin.ruc.backups')->with('success', 'Backup eliminado correctamente.');
    }
}
