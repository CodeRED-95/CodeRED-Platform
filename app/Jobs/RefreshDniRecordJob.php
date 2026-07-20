<?php

namespace App\Jobs;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Domain\Dni\Repositories\DniRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RefreshDniRecordJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $dni) {}

    public function handle(DniProviderInterface $provider, DniRepository $repository): void
    {
        $lock = Cache::lock('dni:refresh:'.hash('sha256', $this->dni), 60);

        if (! $lock->get()) {
            return;
        }

        try {
            if (! $provider->isEnabled()) {
                return;
            }

            $result = $provider->find($this->dni);
            if ($result->status === 'found' && $result->data !== null) {
                $repository->updateOrCreateFromProvider($result->data);
            }
        } finally {
            $lock->release();
        }
    }
}
