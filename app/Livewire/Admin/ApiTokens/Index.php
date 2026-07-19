<?php

namespace App\Livewire\Admin\ApiTokens;

use App\Core\Audit\AuditLogger;
use App\Models\ApiToken;
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
    public array $abilities = ['agencies:read'];

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
    }

    public function updating(): void
    {
        $this->resetPage();
        $this->selectedTokenIds = [];
    }

    public function createToken(AuditLogger $audit): void
    {
        Gate::authorize('api-tokens.create-for-users');
        $allowedAbilities = array_keys((array) config('api.abilities'));
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'expirationDate' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in($allowedAbilities)],
            'targetUserId' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);
        $owner = User::query()->active()->findOrFail($validated['targetUserId']);
        $abilities = array_values(array_unique($validated['abilities']));
        $created = $owner->createToken(trim($validated['name']), $abilities, now()->parse($validated['expirationDate'])->endOfDay());
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
        $this->abilities = ['agencies:read'];
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
        abort_unless($old->tokenable instanceof User, 422);
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
        $query = ApiToken::query()->with(['tokenable', 'creator'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('lower(name) LIKE ?', [$term])
                        ->orWhereHasMorph('tokenable', [User::class], fn (Builder $query) => $query->whereRaw('lower(name) LIKE ?', [$term])->orWhereRaw('lower(email) LIKE ?', [$term]));
                });
            })
            ->when($this->ownerId > 0, fn (Builder $query) => $query->where('tokenable_type', User::class)->where('tokenable_id', $this->ownerId))
            ->when($this->ability !== '', fn (Builder $query) => $query->whereJsonContains('abilities', $this->ability))
            ->when($this->validDate($this->createdFrom), fn (Builder $query) => $query->whereDate('created_at', '>=', $this->createdFrom))
            ->when($this->validDate($this->createdTo), fn (Builder $query) => $query->whereDate('created_at', '<=', $this->createdTo))
            ->when($this->status === 'expired', fn (Builder $query) => $query->where('expires_at', '<=', now()))
            ->when($this->status === 'active', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()->addDays(7))))
            ->when($this->status === 'expiring', fn (Builder $query) => $query->whereBetween('expires_at', [now(), now()->addDays(7)]));
        $tokens = $query->latest()->paginate(15);

        return view('livewire.admin.api-tokens.index', [
            'tokens' => $tokens,
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name', 'email']),
            'availableAbilities' => (array) config('api.abilities'),
        ])->layout('layouts.app', ['pageTitle' => 'API y Tokens']);
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
