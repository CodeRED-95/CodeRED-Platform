<?php

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Models\RucImport;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RucImportErrorsController
{
    public function __invoke(RucImport $import): StreamedResponse
    {
        Gate::authorize('ruc.view-errors');

        return response()->streamDownload(function () use ($import): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['linea', 'motivo', 'vista_previa']);
            foreach ($import->errors()->orderBy('id')->lazyById(1000) as $error) {
                fputcsv($handle, [$error->line_number, $error->reason, $error->line_preview]);
            }
            fclose($handle);
        }, 'errores-ruc-'.$import->uuid.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
