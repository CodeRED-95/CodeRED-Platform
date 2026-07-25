<?php

namespace App\Modules\Agencies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyImportRunDownloadController extends Controller
{
    public function __invoke(Request $request, AgencyImportRun $importRun, string $file): StreamedResponse
    {
        Gate::authorize('import', \App\Modules\Agencies\Models\Agency::class);
        abort_unless($importRun->created_by === $request->user()->id || $request->user()->hasPermission('agencies.import'), 403);

        $path = match ($file) {
            'processed' => $importRun->extracted_json_path,
            'report' => $importRun->report_json_path,
            default => null,
        };

        abort_unless($path && Storage::exists($path), 404);

        return Storage::download($path, basename($path));
    }
}
