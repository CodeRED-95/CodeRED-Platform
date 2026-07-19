<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class BulkDeleteAgenciesAction
{
    /** @param array<int, int> $agencyIds @return array{deleted: int, ignored: int, unauthorized: int, restricted: int, errors: int} */
    public function execute(array $agencyIds): array
    {
        $agencies = Agency::query()->whereIn('id', $agencyIds)->orderBy('id')->get();
        $agencies->each(fn (Agency $agency) => Gate::authorize('delete', $agency));

        $deleted = DB::transaction(function () use ($agencies): int {
            $deleted = 0;
            foreach ($agencies->chunk(25) as $chunk) {
                foreach ($chunk as $agency) {
                    $agency->delete();
                    $deleted++;
                }
            }

            return $deleted;
        });

        return ['deleted' => $deleted, 'ignored' => count($agencyIds) - $deleted, 'unauthorized' => 0, 'restricted' => 0, 'errors' => 0];
    }
}
