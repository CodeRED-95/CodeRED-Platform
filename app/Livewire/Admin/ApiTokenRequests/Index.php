<?php

namespace App\Livewire\Admin\ApiTokenRequests;

use App\Actions\ApiTokenRequests\MarkTokenRequestAsDeliveredAction;
use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestType;
use App\Enums\ApiTokenType;
use App\Events\TokenRequestCreated;
use App\Jobs\NotifyN8nTokenRequestStatus;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\ApiTokenRequestEvent;
use App\Models\User;
use App\Services\ApiTokens\ApiTokenGenerator;
use App\Services\ApiTokens\TelegramRequesterLinker;
use App\Services\ApiTokens\TokenVaultService;
use App\Services\Integrations\N8nTelegramTokenSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
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

    public int|float|string $tokenExpiresInDays = ApiTokenGenerator::DEFAULT_EXPIRES_IN_DAYS;

    public int $approvalUserId = 0;

    public string $adminNote = '';

    public string $rejectionReason = '';

    /** @var array{email: string|null, telegram: string|null, whatsapp: string|null} */
    public array $revealedDeliveryContact = ['email' => null, 'telegram' => null, 'whatsapp' => null];

    public bool $deliveryContactRevealed = false;

    public bool $confirmingDelivery = false;
    
    public bool $confirmingManualReveal = false;

    public ?string $manualDeliveryReason = null;

    public ?string $manualDeliveryMethod = null;

    #[Locked]
    public ?string $revealedToken = null;

    public ?int $deleteRequestId = null;

    public bool $manualDeliveryConfirmation = false;
    
    public function confirmManualReveal(TokenVaultService $vault): void
    {
        Gate::authorize('api-token-requests.reveal_token');

        $this->validate([
            'manualDeliveryReason' => ['required', 'string', 'max:500'],
            'manualDeliveryMethod' => ['required', 'string', Rule::in(['presencial', 'llamada', 'canal_corporativo', 'otro'])],
            'manualDeliveryConfirmation' => ['accepted'],
        ]);

        $result = DB::transaction(function () use ($vault) {
            $request = ApiTokenRequest::query()->whereKey($this->selectedId)->lockForUpdate()->firstOrFail();

            if ($request->status !== ApiTokenRequestStatus::Approved) {
                return ['error' => 'La solicitud no está aprobada.'];
            }
            if ($request->token_revealed_at) {
                return ['error' => 'El token ya fue revelado anteriormente.'];
            }
            if (empty($request->token_ciphertext)) {
                return ['error' => 'No hay un token cifrado para revelar.'];
            }
            
            $token = $request->token;
            if (!$token || $token->revoked_at || ($token->expires_at && $token->expires_at->isPast())) {
                return ['error' => 'El token asociado ha sido revocado o ha expirado.'];
            }

            $plainTextToken = $vault->decrypt($request->token_ciphertext);

            $request->forceFill([
                'token_revealed_at' => now(),
                'token_revealed_by_type' => 'admin',
                'token_revealed_by_user_id' => auth()->id(),
                'delivery_status' => ApiTokenRequestDeliveryStatus::Delivered,
                'delivered_at' => now(),
                'delivered_by' => auth()->id(),
                'delivery_method_encrypted' => $vault->encrypt($this->manualDeliveryMethod),
                'delivery_reason_encrypted' => $vault->encrypt($this->manualDeliveryReason),
            ])->save();

            $this->event($request, 'admin_manual_reveal', 'Token revelado manualmente por administrador.', [
                'method' => $this->manualDeliveryMethod,
                'reason' => $this->manualDeliveryReason,
            ]);

            return ['token' => $plainTextToken];
        });

        if (isset($result['error'])) {
            $this->dispatch('toast', type: 'error', message: $result['error']);
        } else {
            $this->revealedToken = $result['token'];
            $this->dispatch('toast', type: 'success', message: 'Token revelado. Entrégalo de forma segura y cierra esta ventana.');
        }

        $this->confirmingManualReveal = false;
    }

    public function closeRevealModal(): void
    {
        $this->revealedToken = null;
        $this->manualDeliveryConfirmation = false;
        $this->manualDeliveryReason = null;
        $this->manualDeliveryMethod = null;
    }

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
        $this->tokenExpiresInDays = $request->requested_token_expires_in_days ?: ApiTokenGenerator::DEFAULT_EXPIRES_IN_DAYS;
        $this->approvalUserId = (int) auth()->id();
        $this->adminNote = '';
        $this->rejectionReason = '';
        $this->revealedDeliveryContact = ['email' => null, 'telegram' => null, 'whatsapp' => null];
        $this->deliveryContactRevealed = false;
        $this->confirmingDelivery = false;
        $this->deleteRequestId = null;
        $this->event($request, 'viewed', 'Solicitud visualizada.');
    }

    public function approve(N8nTelegramTokenSettings $settings, ApiTokenGenerator $generator, \App\Services\ApiTokens\TokenVaultService $vault): void
    {
        Gate::authorize('api-token-requests.approve');

        $current = ApiTokenRequest::query()->findOrFail($this->selectedId);
        if ($current->requestTypeValue() === ApiTokenRequestType::Rotation->value) {
            $this->approveRotation($current, $generator, $vault);

            return;
        }
        $data = $this->validate([
            'approvalTokenName' => ['required', 'string', 'max:100'],
            'approvalTokenType' => ['required', 'string', Rule::in(ApiTokenType::values())],
            'tokenExpiresInDays' => ['required', 'integer', 'min:'.ApiTokenGenerator::MIN_EXPIRES_IN_DAYS, 'max:'.ApiTokenGenerator::MAX_EXPIRES_IN_DAYS],
            'approvalUserId' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'adminNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $current = ApiTokenRequest::query()->findOrFail($this->selectedId);
        if ($current->status !== ApiTokenRequestStatus::Pending) {
            $this->event($current, 'invalid_transition', 'Intento invalido de aprobar una solicitud no pendiente.', ['status' => $current->statusValue()]);
            abort(422, 'La solicitud ya fue procesada.');
        }

        DB::transaction(function () use ($data, $settings, $generator, $vault): void {
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
            $tokenExpiresInDays = (int) $data['tokenExpiresInDays'];
            $expiresAt = $generator->expiresAt($tokenExpiresInDays);
            $created = $generator->create($user, trim($data['approvalTokenName']), $abilities, $tokenExpiresInDays);

            /** @var ApiToken $token */
            $token = ApiToken::query()->findOrFail($created->accessToken->id);
            $token->forceFill([
                'description' => 'Token aprobado desde solicitud '.$request->request_uuid,
                'created_by' => auth()->id(),
            ])->save();

            app(TelegramRequesterLinker::class)->linkFromRequest($request, $user);

            $request->forceFill([
                'requested_token_name' => trim($data['approvalTokenName']),
                'requested_abilities' => $abilities,
                'token_expires_in_days' => $tokenExpiresInDays,
                'token_type' => $tokenType->value,
                'status' => ApiTokenRequestStatus::Approved,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'personal_access_token_id' => $token->id,
                'token_ciphertext' => $vault->encrypt($created->plainTextToken),
                'token_hash' => hash('sha256', $created->plainTextToken),
                'token_last_four' => substr($created->plainTextToken, -4),
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
                'token_expires_in_days' => $tokenExpiresInDays,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
            $this->event($request, 'token_generated', 'Token Sanctum generado sin exponer valor plano.', ['token_type' => $tokenType->value]);
            NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.approved');
        });

        $this->dispatch('toast', type: 'success', message: 'Solicitud aprobada. El token no se muestra en el panel.');
    }

    private function approveRotation(ApiTokenRequest $current, ApiTokenGenerator $generator): void
    {
        $data = $this->validate([
            'adminNote' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($current->status !== ApiTokenRequestStatus::Pending) {
            $this->event($current, 'invalid_transition', 'Intento invalido de aprobar una rotación no pendiente.', ['status' => $current->statusValue()]);
            abort(422, 'La solicitud ya fue procesada.');
        }

        $currentSource = $current->sourceToken;
        if ($currentSource instanceof ApiToken && $currentSource->expires_at?->isPast()) {
            $current->forceFill([
                'status' => ApiTokenRequestStatus::Expired,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'encrypted_plain_text_token' => null,
                'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
            ])->save();
            $this->event($current, 'expired', 'La rotación venció porque el token original expiró antes de aprobarse.', ['source_token_id' => $currentSource->id]);
            abort(422, 'El token original expiró antes de aprobar la rotación.');
        }

        DB::transaction(function () use ($generator, $data): void {
            $request = ApiTokenRequest::query()->whereKey($this->selectedId)->lockForUpdate()->firstOrFail();

            if ($request->status !== ApiTokenRequestStatus::Pending) {
                $this->event($request, 'duplicate_attempt', 'Intento duplicado de aprobación de rotación.', ['status' => $request->statusValue()]);
                abort(422, 'La solicitud ya fue procesada.');
            }

            $source = ApiToken::query()->whereKey($request->source_personal_access_token_id)->lockForUpdate()->first();
            if (! $source instanceof ApiToken) {
                $this->event($request, 'invalid_transition', 'No se encontró el token original de la rotación.');
                abort(422, 'No se encontró el token original.');
            }

            if ($source->revoked_at !== null) {
                $this->event($request, 'invalid_transition', 'El token original ya estaba revocado.', ['source_token_id' => $source->id]);
                abort(422, 'El token original ya fue revocado.');
            }

            if ($source->expires_at?->isPast()) {
                $request->forceFill([
                    'status' => ApiTokenRequestStatus::Expired,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'encrypted_plain_text_token' => null,
                    'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
                ])->save();
                $this->event($request, 'expired', 'La rotación venció porque el token original expiró antes de aprobarse.', ['source_token_id' => $source->id]);
                abort(422, 'El token original expiró antes de aprobar la rotación.');
            }

            if (ApiTokenRequest::query()->where('request_type', ApiTokenRequestType::Rotation->value)->where('source_personal_access_token_id', $source->id)->whereNotNull('replacement_personal_access_token_id')->exists()) {
                $this->event($request, 'duplicate_attempt', 'El token original ya tenía un reemplazo registrado.', ['source_token_id' => $source->id]);
                abort(422, 'El token original ya fue reemplazado.');
            }

            $owner = $source->tokenable;
            abort_unless($owner !== null && method_exists($owner, 'createToken'), 422, 'El propietario del token no puede generar reemplazos.');

            $abilities = array_values($source->abilities ?? []);
            $name = $source->name.' · Rotado '.now()->format('Y-m-d');
            $created = $generator->createWithExpiresAt($owner, $name, $abilities, $source->expires_at);

            /** @var ApiToken $replacement */
            $replacement = ApiToken::query()->findOrFail($created->accessToken->id);
            $replacement->forceFill([
                'description' => 'Token generado por rotación de solicitud '.$request->request_uuid,
                'created_by' => auth()->id(),
            ])->save();

            $source->forceFill([
                'revoked_at' => now(),
                'revoked_by' => auth()->id(),
                'revocation_reason' => 'rotation',
            ])->save();

            $request->forceFill([
                'requested_token_name' => $name,
                'requested_abilities' => $abilities,
                'status' => ApiTokenRequestStatus::Approved,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'personal_access_token_id' => $replacement->id,
                'replacement_personal_access_token_id' => $replacement->id,
                'encrypted_plain_text_token' => Crypt::encryptString($created->plainTextToken),
                'delivery_status' => ApiTokenRequestDeliveryStatus::Pending,
                'metadata' => array_merge($request->metadata ?? [], ['admin_note' => $data['adminNote'] ?? null]),
            ])->save();

            $this->event($request, 'rotation_approved', 'Rotación aprobada.', ['source_token_id' => $source->id, 'replacement_token_id' => $replacement->id, 'expires_at' => $replacement->expires_at?->toIso8601String(), 'token_type' => $request->token_type]);
            $this->event($request, 'source_token_revoked', 'Token anterior revocado por rotación.', ['source_token_id' => $source->id, 'revocation_reason' => 'rotation']);
            $this->event($request, 'replacement_token_generated', 'Token de reemplazo generado sin exponer valor plano.', ['replacement_token_id' => $replacement->id, 'token_type' => $request->token_type]);
            NotifyN8nTokenRequestStatus::dispatch($request->id, 'token_request.rotation.approved');
        });

        $this->dispatch('toast', type: 'success', message: 'Rotación aprobada. El token anterior fue revocado y el reemplazo espera entrega.');
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
        $eventId = $request->webhookDeliveries()
            ->where('event_type', 'token_request.created')
            ->whereNull('delivered_at')
            ->latest()
            ->value('event_id');

        event(new TokenRequestCreated($request, $eventId));
        $this->event($request, 'notification_retry_requested', 'Reintento manual solicitado.', ['event_type' => 'token_request.created']);
        $this->dispatch('toast', type: 'success', message: 'Reintento de notificación enviado a la cola.');
    }

    public function closeSelectedRequest(): void
    {
        $this->selectedId = null;
        $this->revealedDeliveryContact = ['email' => null, 'telegram' => null, 'whatsapp' => null];
        $this->deliveryContactRevealed = false;
        $this->confirmingDelivery = false;
    }

    public function confirmDeleteRequest(int $id): void
    {
        Gate::authorize('api-token-requests.delete');
        $request = ApiTokenRequest::query()->findOrFail($id);
        abort_if($request->isDelivered(), 422, 'No se puede eliminar una solicitud entregada.');
        $this->deleteRequestId = $request->id;
    }

    public function cancelDeleteRequest(): void
    {
        $this->deleteRequestId = null;
    }

    public function deleteConfirmedRequest(): void
    {
        Gate::authorize('api-token-requests.delete');
        $request = ApiTokenRequest::query()->findOrFail($this->deleteRequestId);
        abort_if($request->isDelivered(), 422, 'No se puede eliminar una solicitud entregada.');
        $requestId = $request->id;

        DB::transaction(function () use ($request): void {
            $request->webhookDeliveries()->delete();
            $request->delete();
        });

        if ($this->selectedId === $requestId) {
            $this->closeSelectedRequest();
        }

        $this->deleteRequestId = null;
        $this->dispatch('toast', type: 'success', message: 'Solicitud eliminada.');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->deliveryStatus = '';
        $this->ability = '';
        $this->reviewerId = 0;
        $this->date = '';
        $this->resetPage();
    }

    public function revealDeliveryContact(): void
    {
        Gate::authorize('api-token-requests.view-delivery-contact');
        $request = ApiTokenRequest::query()->findOrFail($this->selectedId);

        if (! $request->canRevealDeliveryContact(auth()->user())) {
            abort(410, 'Los datos de entrega ya no están disponibles.');
        }

        $this->revealedDeliveryContact = $request->deliveryContact();
        $this->deliveryContactRevealed = true;
        $this->event($request, 'delivery_contact_viewed', 'Datos completos de entrega revelados al administrador autorizado.', [
            'fields_viewed' => array_keys(array_filter($this->revealedDeliveryContact)),
            'viewer_id' => auth()->id(),
        ]);
    }

    public function confirmDelivery(): void
    {
        Gate::authorize('api-token-requests.approve');
        $request = ApiTokenRequest::query()->findOrFail($this->selectedId);
        abort_unless($request->statusValue() === ApiTokenRequestStatus::Approved->value, 422, 'Solo una solicitud aprobada puede marcarse como entregada.');
        abort_if($request->isDelivered(), 422, 'La solicitud ya fue marcada como entregada.');
        $this->confirmingDelivery = true;
    }

    public function markSelectedAsDelivered(MarkTokenRequestAsDeliveredAction $action): void
    {
        Gate::authorize('api-token-requests.approve');
        $request = ApiTokenRequest::query()->findOrFail($this->selectedId);
        $action->execute($request, auth()->id(), 'manual');
        $this->revealedDeliveryContact = ['email' => null, 'telegram' => null, 'whatsapp' => null];
        $this->deliveryContactRevealed = false;
        $this->confirmingDelivery = false;
        $this->dispatch('toast', type: 'success', message: 'Solicitud marcada como entregada. Los datos completos fueron eliminados.');
    }

    public function cancelDeliveryConfirmation(): void
    {
        $this->confirmingDelivery = false;
    }

    public function setTokenExpiresInDays(int $days): void
    {
        $this->tokenExpiresInDays = $days;
    }

    public function tokenExpirationPreview(): string
    {
        $days = filter_var($this->tokenExpiresInDays, FILTER_VALIDATE_INT);

        if ($days === false || $days < ApiTokenGenerator::MIN_EXPIRES_IN_DAYS || $days > ApiTokenGenerator::MAX_EXPIRES_IN_DAYS) {
            return 'Ingresa una vigencia entre 1 y 365 días.';
        }

        return app(ApiTokenGenerator::class)->preview($days);
    }

    public function requestDisplayLabel(ApiTokenRequest $request): string
    {
        $prefix = $request->requestTypeValue() === ApiTokenRequestType::Rotation->value ? 'Rotación de ' : 'Generación de ';
        $type = $request->token_type ?? $request->requested_token_type;
        $tokenType = is_string($type) ? ApiTokenType::tryFrom($type) : null;

        return $prefix.($tokenType?->label() ?? 'Token sin preferencia');
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
        $requests = $query->latest('requested_at')->paginate(5);

        return view('livewire.admin.api-token-requests.index', [
            'requests' => $requests,
            'selected' => $this->selectedId ? ApiTokenRequest::query()->with(['events.performer', 'reviewer', 'deliveredBy', 'token', 'webhookDeliveries'])->find($this->selectedId) : null,
            'statuses' => ApiTokenRequestStatus::cases(),
            'deliveryStatuses' => ApiTokenRequestDeliveryStatus::cases(),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'reviewers' => User::query()->orderBy('name')->get(['id', 'name']),
            'availableAbilities' => array_combine(ApiTokenType::allowedAbilities(), ApiTokenType::allowedAbilities()),
            'tokenTypes' => ApiTokenType::options(),
            'tokenExpirationQuickOptions' => [1, 7, 30, 90, 180, 365],
            'tokenExpirationPreview' => $this->tokenExpirationPreview(),
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
