<?php

namespace App\Livewire\Admin\ApiTokenRequests;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenType;
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

    public string $approvalTokenType = '';

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
        $request = ApiTokenRequest::query()->findOrFail($id);
        $this->selectedId = $request->id;
        $this->approvalTokenName = $request->requested_token_name;
        $this->approvalTokenType = in_array($request->requested_token_type, ApiTokenType::values(), true)
            ? (string) $request->requested_token_type
            : '';
        $this->approvalExpiresInMinutes = $request->requested_expires_in_minutes;
        $this->approvalUserId = (int) auth()->id();
        $this->adminNote = '';
        $this->rejectionReason = '';
        $this->event($request, 'viewed', 'Solicitud visualizada.');
    }

    public function approve(N8nTelegramTokenSettings $settings): void
    {
        Gate::authorize('api-token-requests.approve');

        $data = $this->validate([
            'approvalTokenName' => ['required', 'string', 'max:100'],
            'approvalTokenType' => ['required', 'string', Rule::in(ApiTokenType::values())],
            'approvalExpiresInMinutes' => ['required', 'integer', 'min:1', 'max:'.(int) $settings->get('max_expires_in_minutes', 1440)],
            'approvalUserId' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'adminNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $current = ApiTokenRequest::query()->findOrFail($this->selectedId);
        if ($current->status !== ApiTokenRequestStatus::Pending) {
            $this->event($current, 'invalid_transition', 'Intento invalido de aprobar una solicitud no pendiente.', ['status' => $current->statusValue()]);
            abort(422, 'La solicitud ya fue procesada.');
        }

        DB::transaction(function () use ($data, $settings): void {
            $request = ApiTokenRequest::query()->whereKey($this->selectedId)->lockForUpdate()->firstOrFail();

            if ($request->status !== ApiTokenRequestStatus::Pending) {
                abort(422, 'La solicitud ya fue procesada.');
            }

            $requestedAt = $request->requestedAt();
            if ($requestedAt?->lt(now()->subMinutes((int) $settings->get('approval_timeout_minutes', 1440)))) {
                $this->event($request, 'invalid_transition', 'Intento invalido de aprobar una solicitud vencida.', ['status' => $request->statusValue()]);
                abort(422, 'La solicitud ya venció.');
            }

            $tokenType = ApiTokenType::from($data['approvalTokenType']);
            $abilities = $tokenType->abilities();
            $user = User::query()->active()->findOrFail($data['approvalUserId']);
            $expiresAt = now()->addMinutes((int) $data['approvalExpiresInMinutes']);
            $created = $user->createToken(trim($data['approvalTokenName']), $abilities, $expiresAt);

            /** @var ApiToken $token */
            $token = ApiToken::query()->findOrFail($created->accessToken->id);
            $token->forceFill([
                'description' => 'Token aprobado desde solicitud '.$request->request_uuid,
                'created_by' => auth()->id(),
            ])->save();

            $request->forceFill([
                'requested_token_name' => trim($data['approvalTokenName']),
                'requested_abilities' => $abilities,
                'requested_expires_in_minutes' => (int) $data['approvalExpiresInMinutes'],
                'token_type' => $tokenType->value,
                'status' => ApiTokenRequestStatus::Approved,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'personal_access_token_id' => $token->id,
                'encrypted_plain_text_token' => Crypt::encryptString($created->plainTextToken),
                'delivery_status' => ApiTokenRequestDeliveryStatus::Pending,
            ])->save();

            $this->event($request, 'token_type_selected', 'Tipo de token seleccionado.', [
                'token_type' => $tokenType->value,
                'abilities' => $abilities,
            ]);

            if ($request->requested_token_type !== null && $request->requested_token_type !== $tokenType->value) {
                $this->event($request, 'token_type_changed', 'El administrador aprobó un tipo distinto al solicitado.', [
                    'requested_token_type' => $request->requested_token_type,
                    'token_type' => $tokenType->value,
                ]);
            }

            $this->event($request, 'approved', 'Solicitud aprobada.', [
                'token_type' => $tokenType->value,
                'abilities' => $abilities,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
            $this->event($request, 'token_generated', 'Token Sanctum generado sin exponer valor plano.', ['token_type' => $tokenType->value]);
            NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.approved');
        });

        $this->dispatch('toast', type: 'success', message: 'Solicitud aprobada. El token no se muestra en el panel.');
    }

    public function reject(): void
    {
        Gate::authorize('api-token-requests.reject');
        $data = $this->validate(['rejectionReason' => ['nullable', 'string', 'max:1000']]);
        $request = ApiTokenRequest::query()->findOrFail($this->selectedId);

        if ($request->status !== ApiTokenRequestStatus::Pending) {
            $this->event($request, 'invalid_transition', 'Intento invalido de rechazar una solicitud no pendiente.', ['status' => $request->statusValue()]);
            abort(422, 'La solicitud ya fue procesada.');
        }

        $reason = trim((string) ($data['rejectionReason'] ?? ''));
        $request->forceFill([
            'status' => ApiTokenRequestStatus::Rejected,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejected_at' => now(),
            'rejection_reason' => $reason === '' ? null : $reason,
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
            'encrypted_plain_text_token' => null,
        ])->save();
        $this->event($request, 'rejected', 'Solicitud rechazada.');
        if ($reason !== '') {
            $this->event($request, 'rejection_reason_recorded', 'Motivo del rechazo registrado.', ['reason' => $reason]);
        }
        NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.rejected');
        $this->dispatch('toast', type: 'success', message: 'Solicitud rechazada.');
    }

    public function cancel(int $id): void
    {
        Gate::authorize('api-token-requests.cancel');
        $request = ApiTokenRequest::query()->findOrFail($id);
        abort_if($request->status !== ApiTokenRequestStatus::Pending, 422);
        $request->update(['status' => ApiTokenRequestStatus::Cancelled, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'encrypted_plain_text_token' => null]);
        $this->event($request, 'cancelled', 'Solicitud cancelada.');
        NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.cancelled');
    }

    public function expire(int $id): void
    {
        Gate::authorize('api-token-requests.cancel');
        $request = ApiTokenRequest::query()->findOrFail($id);
        abort_if($request->status !== ApiTokenRequestStatus::Pending, 422);
        $request->update(['status' => ApiTokenRequestStatus::Expired, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'encrypted_plain_text_token' => null]);
        $this->event($request, 'expired', 'Solicitud marcada como vencida.');
        NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.expired');
    }

    public function revoke(int $id): void
    {
        Gate::authorize('api-token-requests.revoke');
        $request = ApiTokenRequest::query()->findOrFail($id);
        $request->token?->delete();
        $request->update(['encrypted_plain_text_token' => null, 'delivery_status' => ApiTokenRequestDeliveryStatus::Failed]);
        $this->event($request, 'token_revoked', 'Token revocado.');
        NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.revoked');
    }

    public function retryNotification(int $id): void
    {
        Gate::authorize('api-token-requests.retry-notification');
        $request = ApiTokenRequest::query()->findOrFail($id);
        NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.'.$request->statusValue());
        $this->event($request, 'notification_retry_requested', 'Reintento manual solicitado.');
    }

    public function render(): View
    {
        $query = ApiTokenRequest::query()->with(['reviewer', 'token'])->when($this->search !== '', function (Builder $query): void {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(fn (Builder $search): Builder => $search
                ->whereRaw('lower(request_uuid) LIKE ?', [$term])
                ->orWhereRaw("lower(coalesce(requester_name, telegram_username, '')) LIKE ?", [$term])
                ->orWhereRaw("lower(coalesce(application_name, requested_token_name, '')) LIKE ?", [$term])
                ->orWhereRaw('lower(telegram_user_id) LIKE ?', [$term])
            );
        })
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->when($this->deliveryStatus !== '', fn (Builder $query): Builder => $query->where('delivery_status', $this->deliveryStatus))
            ->when($this->date !== '', fn (Builder $query): Builder => $query->whereDate('requested_at', $this->date))
            ->when($this->ability !== '', fn (Builder $query): Builder => $query->whereJsonContains('requested_abilities', $this->ability))
            ->when($this->reviewerId > 0, fn (Builder $query): Builder => $query->where('reviewed_by', $this->reviewerId));
        $requests = $query->latest('requested_at')->paginate(15);

        return view('livewire.admin.api-token-requests.index', [
            'requests' => $requests,
            'selected' => $this->selectedId ? ApiTokenRequest::query()->with(['events.performer', 'reviewer', 'token'])->find($this->selectedId) : null,
            'statuses' => ApiTokenRequestStatus::cases(),
            'deliveryStatuses' => ApiTokenRequestDeliveryStatus::cases(),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'reviewers' => User::query()->orderBy('name')->get(['id', 'name']),
            'availableAbilities' => array_combine(ApiTokenType::allowedAbilities(), ApiTokenType::allowedAbilities()),
            'tokenTypes' => ApiTokenType::options(),
            'summary' => $this->summary(),
        ])->layout('layouts.app', ['pageTitle' => 'Solicitudes de tokens']);
    }

    private function summary(): array
    {
        return [
            'pending' => ApiTokenRequest::query()->where('status', 'pending')->count(),
            'approved_today' => ApiTokenRequest::query()->where('status', 'approved')->whereDate('approved_at', today())->count(),
            'rejected_today' => ApiTokenRequest::query()->where('status', 'rejected')->whereDate('rejected_at', today())->count(),
            'delivered' => ApiTokenRequest::query()->where('delivery_status', 'delivered')->count(),
            'expired' => ApiTokenRequest::query()->where('status', 'expired')->count(),
            'active_telegram_tokens' => ApiTokenRequest::query()->where('status', 'approved')->whereHas('token', fn (Builder $query): Builder => $query->whereNull('revoked_at')->where(fn (Builder $status): Builder => $status->whereNull('expires_at')->orWhere('expires_at', '>', now())))->count(),
        ];
    }

    private function event(ApiTokenRequest $request, string $event, string $description, array $metadata = []): void
    {
        ApiTokenRequestEvent::query()->create([
            'api_token_request_id' => $request->id,
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata,
            'performed_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
