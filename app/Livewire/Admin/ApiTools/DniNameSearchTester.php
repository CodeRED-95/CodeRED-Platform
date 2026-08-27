<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ApiTools;

use App\Core\Api\Enums\ApiRequestType;
use App\Domain\DniNameSearch\Data\DniNameMatch;
use App\Domain\DniNameSearch\Data\DniNameSearchResult;
use App\Models\ApiRequestLog;
use App\Services\DniNameSearch\DniNameSearchService;
use App\Support\ClipboardPayloadFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Buscador de DNI por nombres para el panel.
 *
 * Consulta el mismo DniNameSearchService que sirve a /api/v1/dni/name-search,
 * de modo que el panel y la API comparten proveedor, caché y estados: lo que se
 * ve aquí es lo que devolvería un token con la ability dni:nombre.
 *
 * Se autoriza con dni-records.view —el permiso RBAC al que ya se mapea esa
 * ability— en lugar de crear uno nuevo: así retirar el permiso corta a la vez
 * el acceso por panel y por API.
 */
class DniNameSearchTester extends Component
{
    public string $nombres = '';

    public string $apellidoPaterno = '';

    public string $apellidoMaterno = '';

    /** @var list<array<string, string>>|null */
    public ?array $matches = null;

    /** @var array<string, mixed>|null */
    public ?array $technical = null;

    public ?string $errorMessage = null;

    public ?string $copyJson = null;

    public ?string $copyDataText = null;

    public function mount(): void
    {
        Gate::authorize('dni-records.view');
    }

    public function search(DniNameSearchService $service): void
    {
        Gate::authorize('dni-records.view');

        // Mismas reglas que DniNameSearchRequest: el panel no puede aceptar
        // entradas que la API rechazaría, o dejaría de ser una prueba fiel.
        $this->validate([
            'nombres' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L} .\'-]+$/u'],
            'apellidoPaterno' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L} .\'-]+$/u'],
            'apellidoMaterno' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L} .\'-]+$/u'],
        ], [
            'nombres.regex' => 'Los nombres solo admiten letras, espacios, apóstrofos y guiones.',
            'apellidoPaterno.regex' => 'El apellido paterno solo admite letras, espacios, apóstrofos y guiones.',
            'apellidoMaterno.regex' => 'El apellido materno solo admite letras, espacios, apóstrofos y guiones.',
        ], [
            'apellidoPaterno' => 'apellido paterno',
            'apellidoMaterno' => 'apellido materno',
        ]);

        if (! RateLimiter::attempt('admin-dni-name-search:'.auth()->id(), 20, fn () => true, 60)) {
            $this->setError('Se superó el límite temporal del buscador.', 429, null, null);

            return;
        }

        $this->reset(['matches', 'technical', 'errorMessage', 'copyJson', 'copyDataText']);

        $started = hrtime(true);
        $result = $service->search($this->nombres, $this->apellidoPaterno, $this->apellidoMaterno);
        $elapsed = (int) round((hrtime(true) - $started) / 1_000_000);
        $status = $this->statusFor($result);

        $this->log($result, $status, $elapsed);

        if ($result->status !== 'found') {
            $this->setError($result->message ?? 'No fue posible completar la búsqueda.', $status, $elapsed, $result);

            return;
        }

        $data = array_map(static fn (DniNameMatch $match): array => $match->toArray(), $result->matches);
        $this->matches = $data;
        $this->copyDataText = ClipboardPayloadFormatter::readable($data);
        $this->copyJson = ClipboardPayloadFormatter::json([
            'success' => true,
            'data' => $data,
            'meta' => ['provider' => 'dniperu', 'official' => false, 'referential' => true, 'count' => count($data)],
        ]);
        $this->technical = $this->technicalDetails($status, $elapsed, $result, count($data));
    }

    public function clear(): void
    {
        $this->reset(['nombres', 'apellidoPaterno', 'apellidoMaterno', 'matches', 'technical', 'errorMessage', 'copyJson', 'copyDataText']);
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.api-tools.dni-name-search-tester', [
            'featureEnabled' => (bool) config('dni.name_search.enabled', false),
            'providerEnabled' => (bool) config('dni.name_search.providers.dniperu.enabled', false),
            'cacheEnabled' => (bool) config('dni.name_search.cache_enabled', true),
        ])->layout('layouts.app', ['pageTitle' => 'Buscar DNI por nombres']);
    }

    /**
     * El log guarda el hash de la combinación consultada, nunca los nombres:
     * es el mismo criterio que aplica AuditApiRequest al endpoint público.
     */
    private function log(DniNameSearchResult $result, int $status, int $elapsed): void
    {
        ApiRequestLog::query()->create([
            'request_type' => ApiRequestType::AdminTest->value,
            'service' => 'dni-name-search',
            'endpoint' => '/admin/api-tools/dni-name-search',
            'method' => 'INTERNAL',
            'status_code' => $status,
            'identifier_hash' => $this->identifierHash(),
            'response_time_ms' => $elapsed,
            'source' => $result->status === 'found' ? ($result->cacheHit ? 'cache' : 'dniperu') : null,
            'provider_called' => $result->status !== 'provider_disabled' && ! $result->cacheHit,
            'provider_status_code' => $result->statusCode,
            'cache_hit' => $result->cacheHit,
            'local_database_hit' => false,
            'created_at' => now(),
        ]);
    }

    private function identifierHash(): string
    {
        return hash('sha256', implode('|', array_map(
            static fn (string $value): string => mb_strtoupper(trim($value)),
            [$this->nombres, $this->apellidoPaterno, $this->apellidoMaterno],
        )));
    }

    private function setError(string $message, int $status, ?int $elapsed, ?DniNameSearchResult $result): void
    {
        $this->errorMessage = $message;
        $this->matches = null;
        $this->copyJson = null;
        $this->copyDataText = null;
        $this->technical = $this->technicalDetails($status, $elapsed, $result, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalDetails(int $status, ?int $elapsed, ?DniNameSearchResult $result, int $count): array
    {
        return [
            'http_status' => $status,
            'response_time_ms' => $elapsed,
            // $result solo es null cuando cortamos por rate limit antes de
            // llegar al servicio; en cualquier otro caso status siempre existe.
            'result_status' => $result === null ? 'rate_limited' : $result->status,
            'provider_status_code' => $result?->statusCode,
            'cache_hit' => (bool) $result?->cacheHit,
            'provider_called' => $result !== null && $result->status !== 'provider_disabled' && ! $result->cacheHit,
            'match_count' => $count,
            'searched_at' => now()->toIso8601String(),
        ];
    }

    private function statusFor(DniNameSearchResult $result): int
    {
        return match ($result->status) {
            'found' => 200,
            'not_found' => 404,
            'rate_limited' => 429,
            'provider_parse_error' => 502,
            default => 503,
        };
    }
}
