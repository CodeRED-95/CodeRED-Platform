<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Ruc\Jobs\RestoreRucBackupJob;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Services\RucBackupService;
use App\Modules\Shalom\Jobs\DeleteExpiredRecordsJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class QueueJobSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_shalom_expired_records_job_uses_tries_instead_of_retries(): void
    {
        $job = new DeleteExpiredRecordsJob();

        $this->assertSame(3, $job->tries);
        $this->assertObjectNotHasProperty('retries', $job);
    }

    public function test_ruc_restore_job_returns_without_failing_when_operation_no_longer_exists(): void
    {
        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $operation = RucBackupOperation::create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'progress' => 0,
            'message' => 'En cola',
        ]);

        $operation->delete();

        $job = new RestoreRucBackupJob($operation->id);
        $service = Mockery::mock(RucBackupService::class);
        $service->shouldNotReceive('acquireRestoreLock');

        $failedJobsBefore = DB::table('failed_jobs')->count();
        $job->handle($service);

        $this->assertSame($failedJobsBefore, DB::table('failed_jobs')->count());
    }
}
