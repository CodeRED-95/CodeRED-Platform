<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailLog;
use App\Services\Mail\TransactionalEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendTransactionalEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(
        public int $emailLogId,
        public string $type,
        public string $recipient,
        public array $data,
    ) {
        $this->onQueue('default');
    }

    public function handle(TransactionalEmailService $emails): void
    {
        $log = EmailLog::query()->find($this->emailLogId);

        if (! $log instanceof EmailLog) {
            return;
        }

        $emails->send($log, $this->data);
    }

    public function failed(Throwable $exception): void
    {
        EmailLog::query()->whereKey($this->emailLogId)->update([
            'status' => EmailLog::FAILED,
            'failed_at' => now(),
            'failure_category' => 'queue_failed',
        ]);
    }
}
