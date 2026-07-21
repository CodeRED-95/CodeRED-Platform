<?php

namespace App\Livewire\Admin\ApiTools;

use App\Core\Api\Enums\ApiRequestType;
use App\Domain\Dni\Data\DniLookupResult;
use App\Http\Resources\Api\V1\DniResource;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\DniRecord;
use App\Services\Dni\DniLookupService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class DniTester extends Component
{
    public string $dni = '';

    public string $mode = 'internal';

    public int $tokenId = 0;

    public ?array $result = null;

    public ?array $technical = null;

    public ?string $errorMessage = null;

    public ?string $copyJson = null;

    public function mount(): void
    {
        Gate::authorize('api-tools.dni.test');
        $this->tokenId = (int) (ApiToken::query()->whereJsonContains('abilities', 'dni:consultar')->value('id') ?? 0);
    }

    public function consult(DniLookupService $service, Kernel $kernel): void
    {
        Gate::authorize('api-tools.dni.test');
        $this->validate([
            'dni' => ['required', 'regex:/^\d{8}$/'],
            'mode' => ['required', 'in:internal,endpoint'],
            'tokenId' => ['required_if:mode,endpoint', 'integer', 'min:0'],
        ], ['dni.regex' => 'El DNI debe contener exactamente ocho dígitos.']);

        $key = 'admin-dni-test:'.auth()->id();
        if (! RateLimiter::attempt($key, 20, fn () => true, 60)) {
            $this->setError('Se superó el límite temporal del probador.', 429);

            return;
        }

        $this->reset(['result', 'technical', 'errorMessage', 'copyJson']);
        if ($this->mode === 'endpoint') {
            $this->testEndpoint($kernel);

            return;
        }

        $this->testInternal($service);
    }

    public function clear(): void
    {
        $this->reset(['dni', 'result', 'technical', 'errorMessage', 'copyJson']);
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.api-tools.dni-tester', [
            'tokens' => ApiToken::query()
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest()
                ->get(['id', 'name', 'abilities']),
        ])->layout('layouts.app', ['pageTitle' => 'Probar API DNI']);
    }

    private function testInternal(DniLookupService $service): void
    {
        $existedBefore = DniRecord::query()->where('dni', $this->dni)->exists();
        $started = hrtime(true);
        $lookup = $service->find($this->dni);
        $elapsed = $this->elapsed($started);
        $status = $this->statusFor($lookup);

        ApiRequestLog::query()->create(array_merge([
            'request_type' => ApiRequestType::AdminTest->value,
            'service' => 'dni',
            'endpoint' => '/admin/api-tools/dni',
            'method' => 'INTERNAL',
            'status_code' => $status,
            'identifier_hash' => hash('sha256', $this->dni),
            'response_time_ms' => $elapsed,
            'created_at' => now(),
        ], $lookup->audit()));

        if ($lookup->data === null) {
            $this->setError($lookup->message ?? 'No fue posible completar la consulta.', $status, $lookup, $elapsed);

            return;
        }

        $payload = (new DniResource($lookup->data))->resolve(request());
        $this->setSuccess($payload, $lookup->source, $status, $elapsed, [
            'local_database_hit' => $lookup->localDatabaseHit,
            'cache_hit' => $lookup->cacheHit,
            'provider_called' => $lookup->providerCalled,
            'persisted' => ! $existedBefore && DniRecord::query()->where('dni', $this->dni)->exists(),
            'token_name' => null,
            'ability_verified' => false,
        ]);
    }

    private function testEndpoint(Kernel $kernel): void
    {
        $selected = ApiToken::query()->with('tokenable')->findOrFail($this->tokenId);
        if (! in_array('dni:consultar', $selected->abilities ?? [], true)) {
            $this->setError('El token seleccionado no tiene el permiso dni:consultar.', 403);

            return;
        }

        $owner = $selected->tokenable;
        abort_unless(method_exists($owner, 'createToken'), 422);
        $temporary = $owner->createToken('Prueba administrativa efímera', $selected->abilities ?? [], now()->addMinutes(5));
        $temporaryId = (int) $temporary->accessToken->getKey();
        $started = hrtime(true);

        try {
            auth()->forgetGuards();
            $request = Request::create('/api/v1/dni/'.$this->dni, 'GET', server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$temporary->plainTextToken,
            ]);
            $request->attributes->set('request_type', ApiRequestType::AdminTest->value);
            $response = $kernel->handle($request);
            $elapsed = $this->elapsed($started);
            $payload = json_decode((string) $response->getContent(), true);
            $log = ApiRequestLog::query()->where('token_id', $temporaryId)->latest('id')->first();
            $status = $response->getStatusCode();
            if ($log === null) {
                $source = is_array($payload) ? (string) data_get($payload, 'meta.source', 'none') : 'none';
                $log = ApiRequestLog::query()->create([
                    'request_type' => ApiRequestType::AdminTest->value,
                    'api_client_id' => $owner instanceof ApiClient ? $owner->getKey() : null,
                    'token_id' => $temporaryId,
                    'service' => 'dni',
                    'endpoint' => '/api/v1/dni/{dni}',
                    'method' => 'GET',
                    'status_code' => $status,
                    'identifier_hash' => hash('sha256', $this->dni),
                    'response_time_ms' => $elapsed,
                    'source' => $source,
                    'provider_called' => $source === 'perudevs',
                    'cache_hit' => $source === 'cache',
                    'local_database_hit' => $source === 'internal',
                    'created_at' => now(),
                ]);
            }

            if ($status >= 400 || ! is_array($payload) || ($payload['success'] ?? false) !== true) {
                $this->setError(is_array($payload) ? (string) ($payload['message'] ?? 'La prueba del endpoint falló.') : 'La respuesta del endpoint no es JSON válido.', $status, null, $elapsed, $selected->name);

                return;
            }

            $this->setSuccess((array) ($payload['data'] ?? []), (string) data_get($payload, 'meta.source', 'none'), $status, $elapsed, [
                'local_database_hit' => (bool) $log?->local_database_hit,
                'cache_hit' => (bool) $log?->cache_hit,
                'provider_called' => (bool) $log?->provider_called,
                'persisted' => false,
                'token_name' => $selected->name,
                'ability_verified' => true,
            ]);
        } finally {
            $kernel->terminate($request ?? Request::create('/'), $response ?? response());
            ApiToken::query()->whereKey($temporaryId)->delete();
            auth()->forgetGuards();
        }
    }

    private function setSuccess(array $data, string $source, int $status, int $elapsed, array $details): void
    {
        $this->result = $data;
        $this->copyJson = json_encode(['success' => true, 'data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->technical = array_merge([
            'http_status' => $status,
            'response_time_ms' => $elapsed,
            'source' => $source,
            'tested_at' => now()->toIso8601String(),
        ], $details);
    }

    private function setError(string $message, int $status, ?DniLookupResult $lookup = null, ?int $elapsed = null, ?string $tokenName = null): void
    {
        $this->errorMessage = $message;
        $audit = $lookup === null
            ? ['source' => 'none', 'local_database_hit' => false, 'cache_hit' => false, 'provider_called' => false]
            : $lookup->audit();
        $this->technical = [
            'http_status' => $status,
            'response_time_ms' => $elapsed,
            'source' => $audit['source'],
            'local_database_hit' => $audit['local_database_hit'],
            'cache_hit' => $audit['cache_hit'],
            'provider_called' => $audit['provider_called'],
            'persisted' => false,
            'token_name' => $tokenName,
            'ability_verified' => false,
            'tested_at' => now()->toIso8601String(),
        ];
    }

    private function statusFor(DniLookupResult $result): int
    {
        return match ($result->status) {
            'found' => 200,
            'not_found' => 404,
            'invalid_response' => 502,
            default => 503,
        };
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
