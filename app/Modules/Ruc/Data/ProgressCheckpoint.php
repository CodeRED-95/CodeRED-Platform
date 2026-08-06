<?php

namespace App\Modules\Ruc\Data;

class ProgressCheckpoint
{
    public int $linesProcessed;
    public int $recordsInserted;
    public int $errorCount;
    public int $byteOffset;
    public float $elapsedSeconds;
    public float $linesPerSecond;
    public ?int $estimatedTimeLeft;
    public int $memoryUsedMb;
    public int $totalLines;
    public string $message;
    public float $elapsedMilliseconds;
    public float $progressPercentage;

    public function __construct(
        int $linesProcessed,
        int $recordsInserted,
        int $errorCount,
        int $byteOffset,
        float $elapsedSeconds,
        float $linesPerSecond,
        ?int $estimatedTimeLeft,
        int $memoryUsedMb,
        int $totalLines = 0,
        string $message = '',
        float $elapsedMilliseconds = 0,
    ) {
        $this->linesProcessed = $linesProcessed;
        $this->recordsInserted = $recordsInserted;
        $this->errorCount = $errorCount;
        $this->byteOffset = $byteOffset;
        $this->elapsedSeconds = $elapsedSeconds;
        $this->linesPerSecond = $linesPerSecond;
        $this->estimatedTimeLeft = $estimatedTimeLeft;
        $this->memoryUsedMb = $memoryUsedMb;
        $this->totalLines = $totalLines;
        $this->message = $message;
        $this->elapsedMilliseconds = $elapsedMilliseconds;
        $this->progressPercentage = $totalLines > 0 ? min(100, ($linesProcessed / $totalLines) * 100) : 0;
    }
}
