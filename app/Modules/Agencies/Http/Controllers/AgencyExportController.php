<?php

namespace App\Modules\Agencies\Http\Controllers;

use App\Modules\Agencies\Http\Requests\AgencyExportRequest;
use App\Modules\Agencies\Services\AgencyExportService;
use Illuminate\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyExportController
{
    public function __invoke(AgencyExportRequest $request, AgencyExportService $service): StreamedResponse
    {
        $user = $request->user();
        abort_if(RateLimiter::tooManyAttempts('agency-exports:'.$user->getKey(), 5), 429, 'Se alcanzó el límite de exportaciones.');
        RateLimiter::hit('agency-exports:'.$user->getKey(), 60);
        $lock = Cache::lock('agency-export:user:'.$user->getKey(), 120);
        abort_unless($lock->get(), 429, 'Ya existe una exportación en curso.');

        try {
            $validated = $request->validated();
            $filtered = $validated['scope'] === 'filtered';
            unset($validated['scope']);
            $response = $service->download($validated, $filtered, $user);
            $callback = $response->getCallback();
            $response->setCallback(function () use ($callback, $lock): void {
                try {
                    $callback();
                } finally {
                    $lock->release();
                }
            });

            return $response;
        } catch (\Throwable $exception) {
            $this->release($lock);
            throw $exception;
        }
    }

    private function release(Lock $lock): void
    {
        $lock->release();
    }
}
