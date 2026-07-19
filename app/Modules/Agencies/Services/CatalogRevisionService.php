<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencySyncChange;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

final class CatalogRevisionService
{
    public function __construct(private readonly AgencySyncCursor $cursor) {}

    /** @return array{sequence: int, total: int, last_changed_at: ?string, last_update: ?string, last_deletion: ?string, current_cursor: string, catalog_revision: string} */
    public function metadata(): array
    {
        $sequence = (int) (AgencySyncChange::query()->max('id') ?? 0);
        $lastChanged = AgencySyncChange::query()->whereKey($sequence)->value('changed_at');
        $lastUpdate = AgencySyncChange::query()->where('operation', 'upsert')->max('changed_at');
        $lastDeletion = AgencySyncChange::query()->where('operation', 'delete')->max('changed_at');
        $revision = hash('sha256', implode('|', [
            (int) config('api.agency_schema_version'),
            $sequence,
            (string) $lastChanged,
        ]));

        return [
            'sequence' => $sequence,
            'total' => Agency::query()->count(),
            'last_changed_at' => $this->iso($lastChanged),
            'last_update' => $this->iso($lastUpdate),
            'last_deletion' => $this->iso($lastDeletion),
            'current_cursor' => $this->cursor->encode($sequence),
            'catalog_revision' => $revision,
        ];
    }

    public function etag(string $variant = 'metadata'): string
    {
        $sequence = (int) (AgencySyncChange::query()->max('id') ?? 0);

        return '"'.hash('sha256', implode('|', [
            (int) config('api.agency_schema_version'),
            $sequence,
            $variant,
        ])).'"';
    }

    public function isNotModified(Request $request, string $etag): bool
    {
        if (! (bool) config('api.etag_enabled')) {
            return false;
        }

        return in_array($etag, $request->headers->all('if-none-match'), true)
            || in_array(trim($etag, '"'), $request->getETags(), true);
    }

    /** @param array<string, string> $extra */
    public function headers(string $etag, ?string $lastModified = null, array $extra = []): array
    {
        $headers = [
            'Cache-Control' => 'private, must-revalidate',
            'Vary' => 'Authorization, Accept-Encoding',
        ];
        if ((bool) config('api.etag_enabled')) {
            $headers['ETag'] = $etag;
        }
        if ((bool) config('api.last_modified_enabled') && $lastModified !== null) {
            $headers['Last-Modified'] = Carbon::parse($lastModified)->toRfc7231String();
        }

        return [...$headers, ...$extra];
    }

    public function notModified(string $etag, ?string $lastModified = null): Response
    {
        return response('', 304, $this->headers($etag, $lastModified));
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->utc()->toIso8601String();
    }
}
