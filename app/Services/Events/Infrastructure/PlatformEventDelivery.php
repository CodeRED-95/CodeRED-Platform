<?php

namespace App\Services\Events\Infrastructure;

use App\Models\EventDispatch;
use App\Services\Events\Contracts\EventDeliveryContract;
use App\Services\Events\Contracts\EventDispatchRepositoryContract;
use App\Services\Events\Contracts\EventTransportContract;
use App\Services\Events\DTO\EventData;
use App\Services\Events\Exceptions\EventDispatchException;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PlatformEventDelivery implements EventDeliveryContract
{
    public function __construct(
        private readonly EventTransportContract $transport,
        private readonly EventDispatchRepositoryContract $repository,
    ) {}

    public function deliver(EventDispatch $dispatch, EventData $event): bool
    {
        $maxAttempts = max((int) config('events.retry', 5), 1);
        $lastException = null;
        $startedAt = hrtime(true);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->repository->markProcessing($dispatch, $attempt);

            try {
                $this->transport->send($event);
                $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
                $this->repository->markSent($dispatch, 200, json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $attempt, $durationMs);

                return true;
            } catch (Throwable $exception) {
                $lastException = $exception;
                $responseCode = $exception instanceof EventDispatchException && is_int($exception->getCode()) && $exception->getCode() > 0 ? $exception->getCode() : null;
                $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

                $this->repository->markFailed($dispatch, $exception->getMessage(), $attempt, $responseCode, null, $durationMs);

                Log::channel('events')->warning('Event dispatch retry failed', [
                    'event_id' => $event->id,
                    'type' => $event->type,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error_class' => $exception::class,
                    'error_code' => $responseCode,
                ]);
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        return false;
    }
}
