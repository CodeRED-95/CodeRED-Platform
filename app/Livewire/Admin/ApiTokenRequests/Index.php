<?php

namespace App\Livewire\Admin\ApiTokenRequests;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Jobs\NotifyN8nTokenRequestStatus;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Models\User;
use App\Services\Integrations\N8nTelegramTokenSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
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
    public string $deliveryStatus = '';

    #[Url]
    public string $date = '';

    #[Url]
    public string $ability = '';

    #[Url]
    public int $reviewerId = 0;

    public ?int $selectedId = null;

    public string $approvalTokenName = '';

    public array $approvalAbilities = [];

    public int $approvalExpiresInMinutes = 60;

    public int $approvalUserId = 0;

    public string $adminNote = '';

    public string $rejectionReason = '';

    public function mount(): void
    {
        Gate::authorize('api-token-requests.view');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function selectRequest(int $id): void
    {
        Gate::authorize('api-token-requests.view');
        $r = ApiTokenRequest::query()->findOrFail($id);
        $this->selectedId = $r->id;
        $this->approvalTokenName = $r->requested_token_name;
        $this->approvalAbilities = $r->requested_abilities ?? [];
        $this->approvalExpiresInMinutes = $r->requested_expires_in_minutes;
        $this->approvalUserId = (int) auth()->id();
        $this->rejectionReason = '';
        $this->event($r, 'viewed', 'Solicitud visualizada.');
    }

    public function approve(N8nTelegramTokenSettings $settings): void
    {
        Gate::authorize('api-token-requests.approve');
        $allowed = $settings->allowedAbilities();
        $data = $this->validate(['approvalTokenName' => ['required', 'string', 'max:100'], 'approvalAbilities' => ['required', 'array', 'min:1'], 'approvalAbilities.*' => ['required', 'string', Rule::in($allowed), 'not_in:*'], 'approvalExpiresInMinutes' => ['required', 'integer', 'min:1', 'max:'.(int) $settings->get('max_expires_in_minutes', 1440)], 'approvalUserId' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')], 'adminNote' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($data): void {
            $r = ApiTokenRequest::query()->whereKey($this->selectedId)->lockForUpdate()->firstOrFail();
            if ($r->status !== ApiTokenRequestStatus::Pending) {
                abort(422, 'La solicitud ya fue procesada.');
            } if ($r->requestedAt()?->lt(now()->subMinutes(app(N8nTelegramTokenSettings::class)->get('approval_timeout_minutes', 1440)))) {
                abort(422, 'La solicitud ya venció.');
            } $user = User::query()->active()->findOrFail($data['approvalUserId']);
            $expiresAt = now()->addMinutes((int) $data['approvalExpiresInMinutes']);
            $created = $user->createToken(trim($data['approvalTokenName']), array_values(array_unique($data['approvalAbilities'])), $expiresAt);
            /** @var ApiToken $token */ $token = ApiToken::query()->findOrFail($created->accessToken->id);
            $token->forceFill(['description' => 'Token aprobado desde solicitud Telegram '.$r->request_uuid, 'created_by' => auth()->id()])->save();
            $r->forceFill(['requested_token_name' => trim($data['approvalTokenName']), 'requested_abilities' => array_values(array_unique($data['approvalAbilities'])), 'requested_expires_in_minutes' => (int) $data['approvalExpiresInMinutes'], 'status' => ApiTokenRequestStatus::Approved, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'approved_at' => now(), 'personal_access_token_id' => $token->id, 'encrypted_plain_text_token' => Crypt::encryptString($created->plainTextToken), 'delivery_status' => ApiTokenRequestDeliveryStatus::Pending])->save();
            $this->event($r, 'approved', 'Solicitud aprobada.', ['abilities' => $data['approvalAbilities'], 'expires_at' => $expiresAt->toIso8601String()]);
            $this->event($r, 'token_generated', 'Token Sanctum generado sin exponer valor plano.');
            NotifyN8nTokenRequestStatus::dispatch($r->id, 'token_request.approved');
        });
        $this->dispatch('toast', type: 'success', message: 'Solicitud aprobada. El token no se muestra en el panel.');
    }

    public function reject(): void
    {
        Gate::authorize('api-token-requests.reject');
        $data = $this->validate(['rejectionReason' => ['required', 'string', 'min:10', 'max:1000']]);
        $r = ApiTokenRequest::query()->findOrFail($this->selectedId);
        abort_if($r->status !== ApiTokenRequestStatus::Pending, 422);
        $r->forceFill(['status' => ApiTokenRequestStatus::Rejected, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'rejected_at' => now(), 'rejection_reason' => trim($data['rejectionReason']), 'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable, 'encrypted_plain_text_token' => null])->save();
        $this->event($r, 'rejected', 'Solicitud rechazada.');
        NotifyN8nTokenRequestStatus::dispatch($r->id, 'token_request.rejected');
        $this->dispatch('toast', type: 'success', message: 'Solicitud rechazada.');
    }

    public function cancel(int $id): void
    {
        Gate::authorize('api-token-requests.cancel');
        $r = ApiTokenRequest::query()->findOrFail($id);
        abort_if($r->status !== ApiTokenRequestStatus::Pending, 422);
        $r->update(['status' => ApiTokenRequestStatus::Cancelled, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'encrypted_plain_text_token' => null]);
        $this->event($r, 'cancelled', 'Solicitud cancelada.');
        NotifyN8nTokenRequestStatus::dispatch($r->id, 'token_request.cancelled');
    }

    public function expire(int $id): void
    {
        Gate::authorize('api-token-requests.cancel');
        $r = ApiTokenRequest::query()->findOrFail($id);
        abort_if($r->status !== ApiTokenRequestStatus::Pending, 422);
        $r->update(['status' => ApiTokenRequestStatus::Expired, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'encrypted_plain_text_token' => null]);
        $this->event($r, 'expired', 'Solicitud marcada como vencida.');
        NotifyN8nTokenRequestStatus::dispatch($r->id, 'token_request.expired');
    }

    public function revoke(int $id): void
    {
        Gate::authorize('api-token-requests.revoke');
        $r = ApiTokenRequest::query()->findOrFail($id);
        $r->token?->delete();
        $r->update(['encrypted_plain_text_token' => null, 'delivery_status' => ApiTokenRequestDeliveryStatus::Failed]);
        $this->event($r, 'token_revoked', 'Token revocado.');
        NotifyN8nTokenRequestStatus::dispatch($r->id, 'token_request.revoked');
    }

    public function retryNotification(int $id): void
    {
        Gate::authorize('api-token-requests.retry-notification');
        $r = ApiTokenRequest::query()->findOrFail($id);
        NotifyN8nTokenRequestStatus::dispatch($r->id, 'token_request.'.$r->statusValue());
        $this->event($r, 'notification_retry_requested', 'Reintento manual solicitado.');
    }

    public function render(): View
    {
        $query = ApiTokenRequest::query()->with(['reviewer', 'token'])->when($this->search !== '', function (Builder $q) {
            $t = '%'.mb_strtolower(trim($this->search)).'%';
            $q->where(fn ($s) => $s->whereRaw('lower(request_uuid) LIKE ?', [$t])->orWhereRaw('lower(telegram_username) LIKE ?', [$t])->orWhereRaw('lower(telegram_user_id) LIKE ?', [$t])->orWhereRaw('lower(requested_token_name) LIKE ?', [$t]));
        })->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->when($this->deliveryStatus !== '', fn ($q) => $q->where('delivery_status', $this->deliveryStatus))->when($this->date !== '', fn ($q) => $q->whereDate('requested_at', $this->date))->when($this->ability !== '', fn ($q) => $q->whereJsonContains('requested_abilities', $this->ability))->when($this->reviewerId > 0, fn ($q) => $q->where('reviewed_by', $this->reviewerId));
        $requests = $query->latest('requested_at')->paginate(15);

        return view('livewire.admin.api-token-requests.index', ['requests' => $requests, 'selected' => $this->selectedId ? ApiTokenRequest::query()->with(['events.performer', 'reviewer', 'token'])->find($this->selectedId) : null, 'statuses' => ApiTokenRequestStatus::cases(), 'deliveryStatuses' => ApiTokenRequestDeliveryStatus::cases(), 'users' => User::query()->active()->orderBy('name')->get(['id', 'name']), 'reviewers' => User::query()->orderBy('name')->get(['id', 'name']), 'allowedAbilities' => app(N8nTelegramTokenSettings::class)->allowedAbilities(), 'summary' => $this->summary()])->layout('layouts.app', ['pageTitle' => 'Solicitudes de tokens']);
    }

    private function summary(): array
    {
        return ['pending' => ApiTokenRequest::query()->where('status', 'pending')->count(), 'approved_today' => ApiTokenRequest::query()->where('status', 'approved')->whereDate('approved_at', today())->count(), 'rejected_today' => ApiTokenRequest::query()->where('status', 'rejected')->whereDate('rejected_at', today())->count(), 'delivered' => ApiTokenRequest::query()->where('delivery_status', 'delivered')->count(), 'expired' => ApiTokenRequest::query()->where('status', 'expired')->count(), 'active_telegram_tokens' => ApiTokenRequest::query()->where('status', 'approved')->whereHas('token', fn ($q) => $q->whereNull('revoked_at')->where(fn ($s) => $s->whereNull('expires_at')->orWhere('expires_at', '>', now())))->count()];
    }

    private function event(ApiTokenRequest $r, string $event, string $description, array $metadata = []): void
    {
        ApiTokenRequestEvent::query()->create(['api_token_request_id' => $r->id, 'event' => $event, 'description' => $description, 'metadata' => $metadata, 'performed_by' => auth()->id(), 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now()]);
    }
}
