<?php

namespace App\Services\Events\Contracts;

use App\Models\EventDispatch;
use App\Services\Events\DTO\EventData;

interface EventDispatchRepositoryContract
{
    public function create(EventData $event): EventDispatch;

    public function markProcessing(EventDispatch $dispatch, int $attempt): EventDispatch;

    public function markSent(EventDispatch $dispatch, int $responseCode, string $responseBody, int $attempts, int $durationMs): EventDispatch;

    public function markFailed(EventDispatch $dispatch, string $error, int $attempts, ?int $responseCode = null, ?string $responseBody = null, ?int $durationMs = null): EventDispatch;

    public function findOrFail(int $id): EventDispatch;
}
