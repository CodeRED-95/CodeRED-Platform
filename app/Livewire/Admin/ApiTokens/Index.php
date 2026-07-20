<?php

namespace App\Livewire\Admin\ApiTokens;

use App\Core\Audit\AuditLogger;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\RevokedApiToken;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $ability = '';

    #[Url]
    public int $ownerId = 0;

    #[Url]
    public string $createdFrom = '';

    #[Url]
    public string $createdTo = '';

    public string $name = '';

    public string $description = '';

    public string $expirationDate = '';

    /** @var list<string> */
    public array $abilities = ['agencias:consultar'];

    public int $targetApiClientId = 0;

    public string $clientName = '';

    public string $clientContactName = '';

    public string $clientContactEmail = '';

    #[Url]
    public string $logService = '';

    #[Url]
    public string $logStatus = '';

    #[Url]
    public int $logClientId = 0;

    #[Url]
    public int $logTokenId = 0;

    #[Url]
    public string $logFrom = '';

    #[Url]
    public string $logTo = '';

    public int $targetUserId = 0;

    /** @var list<int> */
    public array $selectedTokenIds = [];

    public ?string $plainTextToken = null;

    public ?string $createdTokenName = null;

    public function mount(): void
    {
        Gate::authorize('api-tokens.view-any');
        $this->expirationDate = now()->addDays((int) config('api.default_token_expiration_days'))->toDateString();
        $this->targetUserId = (int) auth()->id();
        $this->targetApiClientId = (int) (ApiClient::query()->where('active', true)->value('id') ?? 0);
    }

    public function updating(): void
    {
        $this->resetPage();
        $this->selectedTokenIds = [];
    }

    public function createClient(): void
    {
        Gate::authorize('api-tokens.create-for-users');
        $validated = $this->validate([
            'clientName' => ['required', 'string', 'max:120'],
            'clientContactName' => ['nullable', 'string', 'max:120'],
            'clientContactEmail' => ['nullable', 'email', 'max:255'],
        ]);
        $client = ApiClient::query()->create([
            'name' => trim($validated['clientName']),
            'contact_name' => $this->nullableTrim($validated['clientContactName'] ?? null),
            'contact_email' => $this->nullableTrim($validated['clientContactEmail'] ?? null),
            'active' => true,
            'created_by' => auth()->id(),
        ]);
        $this->targetApiClientId = (int) $client->getKey();
        $this->reset(['clientName', 'clientContactName', 'clientContactEmail']);
        $this->dispatch('toast', type: 'success', message: 'Cliente API creado.');
    }

    public function createToken(AuditLogger $audit): void
    {
        Gate::authorize('api-tokens.create-for-users');
        $allowedAbilities = array_keys((array) config('api.abilities'));
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'expirationDate' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'targetApiClientId' => ['nullable', 'integer', 'min:0'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in($allowedAbilities)],
            'targetUserId' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);
        $owner = $this->targetApiClientId > 0
            ? ApiClient::query()->where('active', true)->findOrFail($this->targetApiClientId)
            : User::query()->active()->findOrFail($validated['targetUserId']);
        $abilities = array_values(array_unique($validated['abilities']));
        $created = $owner->createToken(trim($validated['name']), $abilities, filled($validated['expirationDate'] ?? null) ? now()->parse($validated['expirationDate'])->endOfDay() : null);
        /** @var ApiToken $token */
        $token = ApiToken::query()->findOrFail($created->accessToken->getKey());
        $token->forceFill([
            'description' => $this->nullableTrim($validated['description'] ?? null),
            'created_by' => auth()->id(),
        ])->save();
        $audit->log($token, 'api_token_created', [], [
            'name' => $token->name,
            'owner_id' => $owner->id,
            'abilities' => $abilities,
            'expires_at' => $token->expires_at?->toIso8601String(),
        ], ['name', 'owner_id', 'abilities', 'expires_at']);

        $this->plainTextToken = $created->plainTextToken;
        $this->createdTokenName = $token->name;
        $this->reset(['name', 'description']);
        $this->abilities = ['agencias:consultar'];
        $this->dispatch('toast', type: 'success', message: 'Token creado. Cópialo antes de cerrar el aviso.');
    }

    public function dismissPlainToken(): void
    {
        $this->plainTextToken = null;
        $this->createdTokenName = null;
    }

    public function rotateToken(int $tokenId, AuditLogger $audit): void
    {
        Gate::authorize('api-tokens.create-for-users');
        $old = ApiToken::query()->with('tokenable')->findOrFail($tokenId);
        abort_unless($old->tokenable instanceof User || $old->tokenable instanceof ApiClient, 422);
        abort_if(! $old->tokenable->isActive(), 422);
        $created = $old->tokenable->createToken($old->name.' (rotado)', $old->abilities ?? [], now()->addDays((int) config('api.default_token_expiration_days')));
        /** @var ApiToken $new */
        $new = ApiToken::query()->findOrFail($created->accessToken->getKey());
        $new->forceFill(['description' => $old->description, 'created_by' => auth()->id()])->save();
        $audit->log($new, 'api_token_rotated', [], [
            'source_token_id' => $old->id,
            'owner_id' => $old->tokenable_id,
            'abilities' => $new->abilities,
        ], ['source_token_id', 'owner_id', 'abilities']);
        $this->plainTextToken = $created->plainTextToken;
        $this->createdTokenName = $new->name;
        $this->dispatch('toast', type: 'success', message: 'Token rotado. El token anterior sigue activo hasta que lo revoques.');
    }

    public function revokeToken(int $tokenId, AuditLogger $audit): void
    {
        Gate::authorize('api-tokens.revoke-any');
        $token = ApiToken::query()->findOrFail($tokenId);
        $this->auditRevocation($audit, $token, 'api_token_revoked');
        $this->archiveRevocation($token);
        $token->delete();
        $this->selectedTokenIds = array_values(array_diff($this->selectedTokenIds, [$tokenId]));
        $this->dispatch('toast', type: 'success', message: 'El token fue revocado.');
    }

    public function revokeSelected(AuditLogger $audit): void
    {
        Gate::authorize('api-tokens.revoke-any');
        $ids = array_values(array_unique(array_map('intval', $this->selectedTokenIds)));
        abort_if(count($ids) > 100, 422, 'La selección supera el límite de 100 tokens.');
        $tokens = ApiToken::query()->whereKey($ids)->get();

        DB::transaction(function () use ($tokens, $audit): void {
            foreach ($tokens as $token) {
                $this->auditRevocation($audit, $token, 'api_token_bulk_revoked');
                $this->archiveRevocation($token);
                $token->delete();
            }
        });
        $count = $tokens->count();
        $this->selectedTokenIds = [];
        $this->dispatch('toast', type: 'success', message: $count.' tokens fueron revocados.');
    }

    /** @param list<int> $ids */
    public function selectVisible(array $ids): void
    {
        $this->selectedTokenIds = array_values(array_unique(array_map('intval', array_slice($ids, 0, 100))));
    }

    public function clearSelection(): void
    {
        $this->selectedTokenIds = [];
    }

    public function render(): View
    {
        $query = ApiToken::query()->with(['tokenable', 'creator'])->withCount([
            'requestLogs' => fn (Builder $query) => $query->where('request_type', 'api'),
            'requestLogs as agency_requests_count' => fn (Builder $query) => $query->where('request_type', 'api')->where('service', 'agencias'),
            'requestLogs as dni_requests_count' => fn (Builder $query) => $query->where('request_type', 'api')->where('service', 'dni'),
            'requestLogs as successful_requests_count' => fn (Builder $query) => $query->where('request_type', 'api')->whereBetween('status_code', [200, 399]),
            'requestLogs as failed_requests_count' => fn (Builder $query) => $query->where('request_type', 'api')->where('status_code', '>=', 400),
        ])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('lower(name) LIKE ?', [$term])
                        ->orWhereHasMorph('tokenable', [User::class, ApiClient::class], fn (Builder $query) => $query->whereRaw('lower(name) LIKE ?', [$term])->orWhereRaw('lower(email) LIKE ?', [$term]));
                });
            })
            ->when($this->ownerId > 0, fn (Builder $query) => $query->where('tokenable_type', User::class)->where('tokenable_id', $this->ownerId))
            ->when($this->ability !== '', fn (Builder $query) => $query->whereJsonContains('abilities', $this->ability))
            ->when($this->validDate($this->createdFrom), fn (Builder $query) => $query->whereDate('created_at', '>=', $this->createdFrom))
            ->when($this->validDate($this->createdTo), fn (Builder $query) => $query->whereDate('created_at', '<=', $this->createdTo))
            ->when($this->status === 'revoked', fn (Builder $query) => $query->whereNotNull('revoked_at'))
            ->when($this->status === 'expired', fn (Builder $query) => $query->where('expires_at', '<=', now()))
            ->when($this->status === 'active', fn (Builder $query) => $query->whereNull('revoked_at')->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()->addDays(7))))
            ->when($this->status === 'expiring', fn (Builder $query) => $query->whereBetween('expires_at', [now(), now()->addDays(7)]));
        $tokens = $query->latest()->paginate(15);

        $logs = ApiRequestLog::query()->with(['client', 'token'])
            ->when($this->logService !== '', fn (Builder $query) => $query->where('service', $this->logService))
            ->when($this->logStatus !== '' && ctype_digit($this->logStatus), fn (Builder $query) => $query->where('status_code', (int) $this->logStatus))
            ->when($this->logClientId > 0, fn (Builder $query) => $query->where('api_client_id', $this->logClientId))
            ->when($this->logTokenId > 0, fn (Builder $query) => $query->where('token_id', $this->logTokenId))
            ->when($this->validDate($this->logFrom), fn (Builder $query) => $query->whereDate('created_at', '>=', $this->logFrom))
            ->when($this->validDate($this->logTo), fn (Builder $query) => $query->whereDate('created_at', '<=', $this->logTo))
            ->latest('created_at')->paginate(15, ['*'], 'usagePage');

        return view('livewire.admin.api-tokens.index', [
            'logs' => $logs,
            'revokedTokens' => RevokedApiToken::query()->latest('revoked_at')->limit(20)->get(),
            'tokens' => $tokens,
            'clients' => ApiClient::query()->orderBy('name')->get(),
            'usageSummary' => ApiRequestLog::query()->where('request_type', 'api')->selectRaw('service, count(*) as total')->groupBy('service')->pluck('total', 'service'),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name', 'email']),
            'availableAbilities' => (array) config('api.abilities'),
        ])->layout('layouts.app', ['pageTitle' => 'API y Tokens']);
    }

    private function archiveRevocation(ApiToken $token): void
    {
        $token->loadMissing('tokenable');
        RevokedApiToken::query()->create([
            'original_token_id' => $token->id,
            'name' => $token->name,
            'owner_name' => $token->tokenable instanceof User || $token->tokenable instanceof ApiClient
                ? $token->tokenable->name
                : 'Propietario no disponible',
            'abilities' => $token->abilities ?? [],
            'created_at' => $token->created_at,
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
            'revoked_at' => now(),
            'revoked_by' => auth()->id(),
        ]);
    }

    private function auditRevocation(AuditLogger $audit, ApiToken $token, string $action): void
    {
        $audit->log($token, $action, [
            'name' => $token->name,
            'owner_id' => $token->tokenable_id,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
        ], [], ['revoked_at']);
    }

    private function validDate(string $value): bool
    {
        $date = date_create_from_format('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
