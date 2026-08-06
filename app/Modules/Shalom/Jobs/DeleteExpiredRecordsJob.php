<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Jobs;

use App\Modules\Shalom\Models\ShalomDeliveryRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class DeleteExpiredRecordsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $retries = 3;

    public int $timeout = 300;

    public function handle(): void
    {
        $deletedCount = ShalomDeliveryRecord::where('created_at', '<', now()->subDays(90))
            ->delete();

        \Log::info('Deleted expired Shalom delivery records', [
            'count' => $deletedCount,
        ]);
    }
}
