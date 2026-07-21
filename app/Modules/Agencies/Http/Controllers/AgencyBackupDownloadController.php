<?php

namespace App\Modules\Agencies\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Modules\Agencies\Enums\AgencyBackupStatus;
use App\Modules\Agencies\Models\AgencyBackup;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyBackupDownloadController
{
    public function __invoke(AgencyBackup $backup, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('agencies.backup.download');
        abort_unless($backup->status === AgencyBackupStatus::Completed, 404);
        $allowedDirectory = trim((string) config('agency_backups.directory'), '/').'/';
        abort_unless(str_starts_with($backup->path, $allowedDirectory) && basename($backup->path) === $backup->filename, 404);
        abort_unless(Storage::disk($backup->disk)->exists($backup->path), 404);
        $audit->log($backup, 'agency_backup_downloaded', [], ['filename' => $backup->filename], ['filename']);

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename, ['Content-Type' => 'application/json']);
    }
}
