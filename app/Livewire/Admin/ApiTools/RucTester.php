<?php

namespace App\Livewire\Admin\ApiTools;

use App\Core\Api\Enums\ApiRequestType;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\User;
use App\Modules\Ruc\Http\Resources\RucResource;
use App\Modules\Ruc\Services\RucLookupService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RucTester extends Component
{
    public string $ruc = '';

    public string $mode = 'internal';

    public int $tokenId = 0;

    public ?array $result = null;

    public ?array $technical = null;

    public ?string $errorMessage = null;

    public ?string $copyJson = null;

    public function mount(): void
    {
        Gate::authorize('ruc.test');
        $this->tokenId = (int) (ApiToken::query()->whereJsonContains('abilities', 'ruc:consultar')->value('id') ?? 0);
    }

    public function consult(RucLookupService $service, Kernel $kernel): void
    {
        Gate::authorize('ruc.test');
        $this->validate(['ruc' => ['required', 'regex:/^\d{11}$/'], 'mode' => ['required', 'in:internal,endpoint'], 'tokenId' => ['exclude_unless:mode,endpoint', 'required', 'integer', 'min:1']], ['ruc.regex' => 'El RUC debe contener exactamente 11 dígitos.']);
        $this->reset(['result', 'technical', 'errorMessage', 'copyJson']);
        if ($this->mode === 'endpoint') {
            $this->endpoint($kernel);
        } else {
            $this->internal($service);
        }
    }

    public function clear(): void
    {
        $this->reset(['ruc', 'result', 'technical', 'errorMessage', 'copyJson']);
    }

    private function internal(RucLookupService $service): void
    {
        $started = hrtime(true);
        $lookup = $service->find($this->ruc);
        $elapsed = (int) round((hrtime(true) - $started) / 1_000_000);
        $status = $lookup['data'] === null ? 404 : 200;
        ApiRequestLog::query()->create(['request_type' => ApiRequestType::AdminTest, 'service' => 'ruc', 'endpoint' => '/admin/api-tools/ruc', 'method' => 'INTERNAL', 'status_code' => $status, 'identifier_hash' => hash('sha256', $this->ruc), 'response_time_ms' => $elapsed, 'source' => $lookup['source'], 'cache_hit' => $lookup['cached'], 'local_database_hit' => ! $lookup['cached'], 'created_at' => now()]);
        if ($lookup['data'] === null) {
            $this->errorMessage = 'No se encontró el RUC consultado.';
        } else {
            $this->success((new RucResource($lookup['data']))->resolve(request()), $lookup['source'], $status, $elapsed, null, false, $lookup['cached']);
        }
    }

    private function endpoint(Kernel $kernel): void
    {
        $selected = ApiToken::query()->with('tokenable')->findOrFail($this->tokenId);
        if (! in_array('ruc:consultar', $selected->abilities ?? [], true)) {
            $this->errorMessage = 'El token seleccionado no tiene el permiso ruc:consultar.';

            return;
        }
        $owner = $selected->tokenable;
        if (! $owner instanceof User && ! $owner instanceof ApiClient) {
            $this->errorMessage = 'El propietario del token no está disponible.';

            return;
        }
        $temporary = $owner->createToken('Prueba RUC efímera', $selected->abilities, now()->addMinutes(5));
        $started = hrtime(true);
        try {
            auth()->forgetGuards();
            $request = Request::create('/api/v1/ruc/'.$this->ruc, 'GET', server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$temporary->plainTextToken]);
            $request->attributes->set('request_type', ApiRequestType::AdminTest->value);
            $response = $kernel->handle($request);
            $elapsed = (int) round((hrtime(true) - $started) / 1_000_000);
            $payload = json_decode((string) $response->getContent(), true);
            if ($response->getStatusCode() !== 200 || ! is_array($payload)) {
                $this->errorMessage = (string) ($payload['message'] ?? 'La prueba del endpoint falló.');

                return;
            }
            $this->success($payload['data'], (string) ($payload['meta']['source'] ?? 'internal'), 200, $elapsed, $selected->name, true, (bool) ($payload['meta']['cached'] ?? false));
        } finally {
            $kernel->terminate($request ?? Request::create('/'), $response ?? response());
            ApiToken::query()->whereKey($temporary->accessToken->getKey())->delete();
            auth()->forgetGuards();
        }
    }

    private function success(array $data, string $source, int $status, int $elapsed, ?string $token, bool $ability, bool $cached): void
    {
        $this->result = $data;
        $this->copyJson = json_encode(['success' => true, 'message' => 'RUC encontrado.', 'data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->technical = ['source' => $source, 'cached' => $cached, 'http_status' => $status, 'response_time_ms' => $elapsed, 'token_name' => $token, 'ability_verified' => $ability, 'tested_at' => now()->toIso8601String()];
    }

    public function render(): View
    {
        return view('livewire.admin.api-tools.ruc-tester', ['tokens' => ApiToken::query()->whereNull('revoked_at')->latest()->get(['id', 'name', 'abilities'])])->layout('layouts.app', ['pageTitle' => 'Probar API RUC']);
    }
}
