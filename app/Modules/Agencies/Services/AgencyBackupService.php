<?php

namespace App\Modules\Agencies\Services;

use App\Core\Audit\AuditLogger;
use App\Modules\Agencies\Enums\AgencyBackupStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyBackup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AgencyBackupService
{
    public function __construct(private AuditLogger $audit, private AgencyBackupSettingsService $settings) {}

    public function create(?int $actorId = null, ?string $disk = null, ?string $output = null, bool $cleanup = true): AgencyBackup
    {
        $disk ??= (string) config('agency_backups.disk', 'local');
        $now = CarbonImmutable::now('America/Lima');
        $filename = $this->safeFilename($output ?: 'codered-agencies-backup-'.$now->format('Y-m-d-His').'.json');
        $directory = trim((string) config('agency_backups.directory', 'backups/agencies'), '/');
        [$filename, $path] = $this->uniqueDestination($disk, $directory, $filename);
        $temporary = $path.'.part-'.bin2hex(random_bytes(6));
        $backup = AgencyBackup::query()->create([
            'filename' => $filename, 'disk' => $disk, 'path' => $path,
            'status' => AgencyBackupStatus::Processing, 'created_by' => $actorId,
        ]);

        try {
            $filesystem = Storage::disk($disk);
            $filesystem->makeDirectory($directory);
            $absolute = $filesystem->path($temporary);
            $handle = fopen($absolute, 'wb');
            if ($handle === false) {
                throw new RuntimeException('No se pudo abrir el archivo temporal de respaldo.');
            }

            $count = Agency::withTrashed()->count();
            $metadata = [
                'application' => 'CodeRED Platform', 'format' => 'codered-platform', 'module' => 'agencies',
                'type' => 'agency-backup', 'schema_version' => 1,
                'created_at' => $now->toIso8601String(), 'exported_at' => $now->toIso8601String(), 'timezone' => 'America/Lima',
                'database_driver' => config('database.default'), 'record_count' => $count,
            ];
            $this->write($handle, '{"metadata":'.json_encode($metadata, $this->jsonFlags()).',"data":{"agencies":[');
            $first = true;
            foreach (Agency::withTrashed()->orderBy('id')->lazyById(500) as $agency) {
                $attributes = $agency->getAttributes();
                unset($attributes['created_by'], $attributes['updated_by']);
                $this->write($handle, ($first ? '' : ',').json_encode($attributes, $this->jsonFlags()));
                $first = false;
            }
            $this->write($handle, ']}}');
            fclose($handle);

            if (! $filesystem->move($temporary, $path)) {
                throw new RuntimeException('No se pudo publicar atómicamente el respaldo.');
            }
            $absoluteFinal = $filesystem->path($path);
            $backup->update([
                'record_count' => $count,
                'size_bytes' => filesize($absoluteFinal) ?: 0,
                'checksum_sha256' => hash_file('sha256', $absoluteFinal),
                'status' => AgencyBackupStatus::Completed,
            ]);
            $this->audit->log($backup, 'agency_backup_created', [], [
                'filename' => $filename, 'record_count' => $count, 'status' => 'completed',
            ], ['filename', 'record_count', 'status']);
            if ($cleanup && $this->settings->autoCleanup()) {
                $this->cleanup($disk);
            }

            return $backup->refresh();
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($temporary);
            $backup->update(['status' => AgencyBackupStatus::Failed, 'error_message' => 'No fue posible completar la copia.']);
            report($exception);
            throw $exception;
        }
    }

    public function verify(AgencyBackup $backup): string
    {
        $filesystem = Storage::disk($backup->disk);
        if (! $filesystem->exists($backup->path)) {
            $result = 'missing';
        } else {
            $result = hash_file('sha256', $filesystem->path($backup->path)) === $backup->checksum_sha256 ? 'integrity_ok' : 'altered';
        }
        $this->audit->log($backup, 'agency_backup_integrity_checked', [], ['filename' => $backup->filename, 'result' => $result], ['filename', 'result']);

        return $result;
    }

    public function delete(AgencyBackup $backup): void
    {
        Storage::disk($backup->disk)->delete($backup->path);
        $this->audit->log($backup, 'agency_backup_deleted', [], ['filename' => $backup->filename], ['filename']);
        $backup->delete();
    }

    public function cleanup(?string $disk = null): int
    {
        $disk ??= (string) config('agency_backups.disk', 'local');
        $keep = max(1, $this->settings->maximumBackups());
        $cutoff = now()->subDays($this->settings->retentionDays());
        $candidates = AgencyBackup::query()->where('disk', $disk)->where('status', AgencyBackupStatus::Completed)
            ->latest()->skip($keep)->take(1000)->get()->filter(fn (AgencyBackup $backup): bool => $backup->created_at->lt($cutoff));
        foreach ($candidates as $backup) {
            $this->delete($backup);
        }

        return $candidates->count();
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename($filename);
        if (! preg_match('/^[A-Za-z0-9._-]+\.json$/', $filename)) {
            throw new RuntimeException('El nombre de salida debe ser un archivo JSON seguro.');
        }

        return $filename;
    }

    private function uniqueDestination(string $disk, string $directory, string $filename): array
    {
        $filesystem = Storage::disk($disk);
        $path = $directory.'/'.$filename;
        if (! $filesystem->exists($path)) {
            return [$filename, $path];
        }
        $base = pathinfo($filename, PATHINFO_FILENAME);
        for ($suffix = 2; $suffix <= 1000; $suffix++) {
            $candidate = $base.'-'.$suffix.'.json';
            $path = $directory.'/'.$candidate;
            if (! $filesystem->exists($path)) {
                return [$candidate, $path];
            }
        }

        throw new RuntimeException('No se pudo reservar un nombre único para el respaldo.');
    }

    private function write($handle, string $content): void
    {
        if (fwrite($handle, $content) === false) {
            throw new RuntimeException('No se pudo escribir el respaldo.');
        }
    }

    private function jsonFlags(): int
    {
        return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    }
}
