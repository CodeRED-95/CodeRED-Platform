<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Models\RucBackup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupDownloadController
{
    /**
     * Descargar archivo de backup
     */
    public function download(RucBackup $backup): StreamedResponse
    {
        Gate::authorize('download', $backup);

        if (!file_exists($backup->storage_path)) {
            abort(404, 'Archivo de backup no encontrado');
        }

        return response()->download($backup->storage_path, $backup->name);
    }

    /**
     * Listar backups disponibles para descargar
     */
    public function list()
    {
        Gate::authorize('viewAny', RucBackup::class);

        $backups = RucBackup::completed()
            ->latest('created_at')
            ->paginate(20);

        return response()->json($backups);
    }
}
