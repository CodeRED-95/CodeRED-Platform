<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Ruc\Jobs\RestoreRucBackupJob;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Services\RucBackupService;
use App\Modules\Shalom\Jobs\DeleteExpiredRecordsJob;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueJobSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shalom_expired_records_job_uses_tries_instead_of_retries(): void
    {
        $job = new DeleteExpiredRecordsJob;

        $this->assertSame(3, $job->tries);
        $this->assertObjectNotHasProperty('retries', $job);
    }

    public function test_ruc_restore_job_returns_without_failing_when_operation_no_longer_exists(): void
    {
        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $operation = RucBackupOperation::create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'backup_id' => $backup->getKey(),
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'progress' => 0,
            'message' => 'En cola',
        ]);

        $operation->delete();

        $job = new RestoreRucBackupJob($operation->id);
        $service = new class extends RucBackupService
        {
            public function acquireRestoreLock(): Lock
            {
                return new class implements Lock
                {
                    public function name(): string
                    {
                        return 'test-lock';
                    }

                    public function owner(): string
                    {
                        return 'test-owner';
                    }

                    public function get($callback = null): bool
                    {
                        return true;
                    }

                    public function block($seconds, $callback = null): mixed
                    {
                        return $callback instanceof \Closure ? $callback() : true;
                    }

                    public function release(): bool
                    {
                        return true;
                    }

                    public function forceRelease(): void {}
                };
            }
        };

        $failedJobsBefore = DB::table('failed_jobs')->count();
        $job->handle($service);

        $this->assertSame($failedJobsBefore, DB::table('failed_jobs')->count());
    }
}
