<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class BulkDeactivateAgenciesAction
{
    /** @param array<int, int> $agencyIds @return array{deactivated: int, ignored: int, errors: int} */
    public function execute(array $agencyIds): array
    {
        $agencies = Agency::query()->whereIn('id', $agencyIds)->orderBy('id')->get();
        $agencies->each(fn (Agency $agency) => Gate::authorize('manageStatus', $agency));

        $deactivated = DB::transaction(function () use ($agencies): int {
            $deactivated = 0;

            foreach ($agencies->chunk(25) as $chunk) {
                foreach ($chunk as $agency) {
                    if ($agency->status === AgencyStatus::Inactive) {
                        continue;
                    }

                    $agency->status = AgencyStatus::Inactive;
                    $agency->save();
                    $deactivated++;
                }
            }

            return $deactivated;
        });

        return ['deactivated' => $deactivated, 'ignored' => count($agencyIds) - $deactivated, 'errors' => 0];
    }
}
