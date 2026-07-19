<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class BulkRestoreAgenciesAction
{
    /** @param array<int, int> $agencyIds @return array{restored: int, ignored: int, conflicts: int, errors: int} */
    public function execute(array $agencyIds): array
    {
        $agencies = Agency::onlyTrashed()->whereIn('id', $agencyIds)->orderBy('id')->get();
        $agencies->each(fn (Agency $agency) => Gate::authorize('restore', $agency));

        $conflictingIds = $agencies
            ->filter(fn (Agency $agency): bool => $this->hasIdentityConflict($agency))
            ->pluck('id')
            ->all();

        $restorable = $agencies->whereNotIn('id', $conflictingIds);
        $restored = DB::transaction(function () use ($restorable): int {
            $restored = 0;
            foreach ($restorable->chunk(25) as $chunk) {
                foreach ($chunk as $agency) {
                    $agency->restore();
                    $restored++;
                }
            }

            return $restored;
        });

        return [
            'restored' => $restored,
            'ignored' => count($agencyIds) - $agencies->count(),
            'conflicts' => count($conflictingIds),
            'errors' => 0,
        ];
    }

    private function hasIdentityConflict(Agency $agency): bool
    {
        return Agency::query()
            ->whereKeyNot($agency->getKey())
            ->where(function ($query) use ($agency): void {
                $query->where('code', $agency->code)->orWhere('slug', $agency->slug);
                if ($agency->external_id !== null) {
                    $query->orWhere('external_id', $agency->external_id);
                }
            })
            ->exists();
    }
}
