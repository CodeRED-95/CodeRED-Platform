<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class BulkActivateAgenciesAction
{
    /** @param array<int, int> $agencyIds @return array{activated: int, ignored: int, errors: int} */
    public function execute(array $agencyIds): array
    {
        $agencies = Agency::query()->whereIn('id', $agencyIds)->orderBy('id')->get();
        $agencies->each(fn (Agency $agency) => Gate::authorize('manageStatus', $agency));

        $activated = DB::transaction(function () use ($agencies): int {
            $activated = 0;
            foreach ($agencies->chunk(25) as $chunk) {
                foreach ($chunk as $agency) {
                    if ($agency->status !== AgencyStatus::UnderReview) {
                        continue;
                    }
                    $agency->status = AgencyStatus::Active;
                    $agency->save();
                    $activated++;
                }
            }

            return $activated;
        });

        return ['activated' => $activated, 'ignored' => count($agencyIds) - $activated, 'errors' => 0];
    }
}
