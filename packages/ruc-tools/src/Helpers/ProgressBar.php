<?php

namespace RucTool\Helpers;

use Symfony\Component\Console\Helper\ProgressBar as SymfonyProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class ProgressBar
{
    private SymfonyProgressBar $progressBar;

    private int $startTime;

    private int $recordsPerSecond = 0;

    public function __construct(OutputInterface $output, int $max = 0)
    {
        $this->startTime = time();
        $this->progressBar = new SymfonyProgressBar($output, $max);

        $this->progressBar->setFormat(
            '%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% (%speed%)'
        );

        $this->progressBar->setBarCharacter('█');
        $this->progressBar->setEmptyBarCharacter('░');
        $this->progressBar->setProgressCharacter('█');
    }

    public function start(int $max = 0): void
    {
        if ($max > 0) {
            $this->progressBar->setMaxSteps($max);
        }
        $this->progressBar->start();
    }

    public function advance(int $step = 1): void
    {
        $this->progressBar->advance($step);
    }

    public function setProgress(int $current): void
    {
        $this->progressBar->setProgress($current);
    }

    public function finish(): void
    {
        $this->progressBar->finish();
    }

    public function getElapsedTime(): int
    {
        return time() - $this->startTime;
    }

    public function getSpeed(int $processedRecords): string
    {
        $elapsed = $this->getElapsedTime();
        if ($elapsed === 0) {
            return '0 rec/s';
        }

        $speed = intval($processedRecords / $elapsed);

        return "$speed rec/s";
    }

    public function getETA(int $total, int $processed): string
    {
        $elapsed = $this->getElapsedTime();
        if ($processed === 0 || $elapsed === 0) {
            return 'calculating...';
        }

        $remaining = $total - $processed;
        $speed = $processed / $elapsed;
        $eta = intval($remaining / $speed);

        return $this->formatTime($eta);
    }

    private function formatTime(int $seconds): string
    {
        $hours = intval($seconds / 3600);
        $minutes = intval(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        }

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        }

        return "{$secs}s";
    }
}
