<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ApiTools;

use App\Exceptions\SunatRepresentativeException;
use App\Services\Sunat\SunatRepresentativeService;
use App\Support\ClipboardPayloadFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RucRepresentativesTester extends Component
{
    public string $ruc = '';

    /** @var list<array<string, mixed>>|null */
    public ?array $representantes = null;

    /** @var array<string, mixed>|null */
    public ?array $technical = null;

    public ?string $errorMessage = null;

    public ?string $copyJson = null;

    public ?string $copyDataText = null;

    public function mount(): void
    {
        Gate::authorize('ruc.representantes');
    }

    public function consult(SunatRepresentativeService $service): void
    {
        Gate::authorize('ruc.representantes');

        $this->validate([
            'ruc' => ['required', 'regex:/^(10|15|17|20)\d{9}$/'],
        ], [
            'ruc.regex' => 'El RUC debe contener exactamente 11 dígitos y una estructura válida.',
        ]);

        $this->reset(['representantes', 'technical', 'errorMessage', 'copyJson', 'copyDataText']);

        $started = hrtime(true);

        try {
            $result = $service->find($this->ruc);
        } catch (ConnectionException $e) {
            $status = str_contains(mb_strtolower($e->getMessage()), 'timed out') ? 504 : 503;
            $this->setError($status === 504 ? 'SUNAT tardó demasiado en responder.' : 'SUNAT no está disponible.', $status, $started);

            return;
        } catch (RequestException $e) {
            $status = $e->response?->status() >= 500 ? 503 : 502;
            $this->setError($status === 503 ? 'SUNAT no está disponible.' : 'SUNAT respondió de forma inválida.', $status, $started);

            return;
        } catch (SunatRepresentativeException) {
            $this->setError('SUNAT respondió de forma inválida.', 502, $started);

            return;
        }

        $elapsed = $this->elapsed($started);

        if ($result['data'] === null) {
            $this->errorMessage = 'No se encontró información de representantes legales para el RUC consultado.';
            $this->technical = [
                'http_status' => 404,
                'response_time_ms' => $elapsed,
                'cached' => $result['cached'],
                'source' => $result['source'],
                'representative_count' => 0,
                'consulted_at' => $result['consulted_at'],
            ];

            return;
        }

        $this->representantes = array_map(
            static fn ($representative): array => $representative->toArray(),
            $result['data'],
        );
        $this->copyDataText = ClipboardPayloadFormatter::readable($this->representantes);
        $this->copyJson = ClipboardPayloadFormatter::json([
            'success' => true,
            'data' => ['ruc' => $this->ruc, 'representantes' => $this->representantes],
            'meta' => [
                'source' => $result['source'],
                'cached' => $result['cached'],
                'consulted_at' => $result['consulted_at'],
            ],
        ]);
        $this->technical = [
            'http_status' => 200,
            'response_time_ms' => $elapsed,
            'cached' => $result['cached'],
            'source' => $result['source'],
            'representative_count' => count($this->representantes),
            'consulted_at' => $result['consulted_at'],
        ];
    }

    public function clear(): void
    {
        $this->reset(['ruc', 'representantes', 'technical', 'errorMessage', 'copyJson', 'copyDataText']);
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.api-tools.ruc-representatives-tester')
            ->layout('layouts.app', ['pageTitle' => 'Buscar Representantes']);
    }

    private function setError(string $message, int $status, int $started): void
    {
        $this->errorMessage = $message;
        $this->representantes = null;
        $this->copyJson = null;
        $this->copyDataText = null;
        $this->technical = [
            'http_status' => $status,
            'response_time_ms' => $this->elapsed($started),
            'cached' => false,
            'source' => 'sunat',
            'representative_count' => 0,
            'consulted_at' => now()->toISOString(),
        ];
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
