<?php

namespace App\Services\Events\Infrastructure;

use App\Models\EventDispatch;
use App\Services\Events\Contracts\EventDispatchRepositoryContract;
use App\Services\Events\DTO\EventData;

final class EloquentEventDispatchRepository implements EventDispatchRepositoryContract
{
    public function create(EventData $event): EventDispatch
    {
        return EventDispatch::query()->create([
            'event_id' => $event->id,
            'type' => $event->type,
            'status' => 'pending',
            'attempts' => 0,
            'response_code' => null,
            'response_body' => null,
            'error' => null,
            'payload' => $event->payload,
            'occurred_at' => $event->occurredAt,
            'tenant' => $event->tenant,
            'source' => $event->source,
            'duration_ms' => null,
        ]);
    }

    public function markProcessing(EventDispatch $dispatch, int $attempt): EventDispatch
    {
        $dispatch->forceFill([
            'status' => 'processing',
            'attempts' => $attempt,
            'error' => null,
        ])->save();

        return $dispatch->refresh();
    }

    public function markSent(EventDispatch $dispatch, int $responseCode, string $responseBody, int $attempts, int $durationMs): EventDispatch
    {
        $dispatch->forceFill([
            'status' => 'sent',
            'attempts' => $attempts,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'error' => null,
            'duration_ms' => $durationMs,
        ])->save();

        return $dispatch->refresh();
    }

    public function markFailed(EventDispatch $dispatch, string $error, int $attempts, ?int $responseCode = null, ?string $responseBody = null, ?int $durationMs = null): EventDispatch
    {
        $dispatch->forceFill([
            'status' => 'failed',
            'attempts' => $attempts,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'error' => $error,
            'duration_ms' => $durationMs,
        ])->save();

        return $dispatch->refresh();
    }

    public function findOrFail(int $id): EventDispatch
    {
        return EventDispatch::query()->findOrFail($id);
    }
}
