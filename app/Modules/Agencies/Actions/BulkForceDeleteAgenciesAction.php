<?php

namespace App\Modules\Agencies\Actions;

use App\Core\Audit\AuditLogger;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class BulkForceDeleteAgenciesAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<int, int> $agencyIds @return array{deleted: int, ignored: int, restricted: int, errors: int} */
    public function execute(array $agencyIds): array
    {
        $agencies = Agency::onlyTrashed()->whereIn('id', $agencyIds)->orderBy('id')->get();
        $agencies->each(fn (Agency $agency) => Gate::authorize('forceDelete', $agency));

        $deleted = DB::transaction(function () use ($agencies): int {
            $deleted = 0;
            foreach ($agencies->chunk(25) as $chunk) {
                foreach ($chunk as $agency) {
                    $this->auditLogger->log(
                        $agency,
                        'force_deleted',
                        [
                            'internal_id' => $agency->id,
                            'external_id' => $agency->external_id,
                            'code' => $agency->code,
                            'name' => $agency->name,
                        ],
                        [],
                        ['deleted_at'],
                    );
                    $agency->forceDelete();
                    $deleted++;
                }
            }

            return $deleted;
        });

        return [
            'deleted' => $deleted,
            'ignored' => count($agencyIds) - $deleted,
            'restricted' => 0,
            'errors' => 0,
        ];
    }
}
