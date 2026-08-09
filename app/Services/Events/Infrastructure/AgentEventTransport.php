<?php

namespace App\Services\Events\Infrastructure;

use App\Services\Events\Contracts\EventTransportContract;
use App\Services\Events\DTO\EventData;
use App\Services\Events\Exceptions\EventDispatchException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AgentEventTransport implements EventTransportContract
{
    public function send(EventData $event): bool
    {
        $baseUrl = (string) config('events.agent.url');
        $path = (string) config('events.agent.events_path', '/v1/events');
        $timeout = max((int) config('events.timeout', 15), 1);
        $token = (string) config('events.agent.token', '');

        if ($baseUrl === '') {
            throw new EventDispatchException('La URL del agente no está configurada.');
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($token)
                ->post($baseUrl.$path, $event->toArray());

            if (! $response->successful()) {
                throw new EventDispatchException('El agente respondió con estado '.$response->status().'.');
            }

            return true;
        } catch (Throwable $exception) {
            if ($exception instanceof EventDispatchException) {
                throw $exception;
            }

            throw new EventDispatchException('No fue posible enviar el evento al agente.', 0, $exception);
        }
    }
}
