<div class="space-y-6">
    <x-ui.page-header title="Solicitudes de tokens" subtitle="Revisión y entrega segura de tokens solicitados por integraciones." />

    <div class="grid gap-3 md:grid-cols-5">
        <x-ui.stat-card label="Pendientes" :value="$summary['pending']" />
        <x-ui.stat-card label="Aprobadas hoy" :value="$summary['approved_today']" />
        <x-ui.stat-card label="Rechazadas hoy" :value="$summary['rejected_today']" />
        <x-ui.stat-card label="Entregadas" :value="$summary['delivered']" />
        <x-ui.stat-card label="Vencidas" :value="$summary['expired']" />
    </div>

    <x-ui.card title="Filtros">
        <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="Solicitante, app o UUID" />
            <x-ui.dropdown-select id="request-status" wire:model.live="status" label="Estado" :value="$status" :options="['' => 'Todos'] + collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
            <x-ui.dropdown-select id="delivery-status" wire:model.live="deliveryStatus" label="Entrega" :value="$deliveryStatus" :options="['' => 'Todas'] + collect($deliveryStatuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
            <x-ui.dropdown-select id="request-ability" wire:model.live="ability" label="Scope" :value="$ability" :options="['' => 'Todos'] + $availableAbilities" />
            <x-ui.dropdown-select id="request-reviewer" wire:model.live="reviewerId" label="Revisor" :value="$reviewerId" :options="[0 => 'Todos'] + $reviewers->pluck('name', 'id')->all()" />
            <x-ui.input id="request-date" wire:model.live="date" type="date" label="Fecha" />
        </div>
    </x-ui.card>

    <x-ui.table id="token-requests">
        <thead>
            <tr>
                <th class="px-4 py-3">Solicitud</th>
                <th class="px-4 py-3">Solicitante</th>
                <th class="px-4 py-3">Aplicación</th>
                <th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse ($requests as $request)
                <tr class="align-top">
                    <td class="px-4 py-3">
                        <p class="font-mono text-xs">{{ $request->request_uuid }}</p>
                        <p class="text-sm text-[color:var(--color-text-muted)]">{{ $request->requested_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <p>{{ $request->requester_name ?? $request->telegram_username ?? 'Solicitante sin nombre' }}</p>
                        <p class="text-xs text-[color:var(--color-text-muted)]">{{ $request->requester_email ?? $request->requester_phone ?? $request->telegram_user_id }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium">{{ $request->application_name ?? $request->requested_token_name }}</p>
                        <p class="text-xs text-[color:var(--color-text-muted)]">{{ $request->purpose ?? 'Sin motivo registrado' }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm">{{ $this->requestDisplayLabel($request) }}</p>
                        <p class="text-sm">Solicitado: {{ $request->requested_token_type ? strtoupper($request->requested_token_type) : 'Sin preferencia' }}</p>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">Aprobado: {{ $request->token_type ? strtoupper($request->token_type) : 'Pendiente' }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge>{{ $request->status->label() }}</x-ui.badge>
                        <div class="mt-1"><x-ui.badge tone="info">{{ $request->delivery_status->label() }}</x-ui.badge></div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button size="sm" wire:click="selectRequest({{ $request->id }})">Detalles</x-ui.button>
                            @if ($request->status->value === 'pending')
                                <x-ui.button size="sm" variant="ghost" wire:click="cancel({{ $request->id }})">Cancelar</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="expire({{ $request->id }})">Vencer</x-ui.button>
                            @endif
                            @if ($request->personal_access_token_id)
                                <x-ui.button size="sm" variant="danger" wire:click="revoke({{ $request->id }})">Revocar</x-ui.button>
                            @endif
                            <x-ui.button size="sm" variant="secondary" wire:click="retryNotification({{ $request->id }})">Reintentar aviso</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-8"><x-ui.empty-state title="No hay solicitudes" description="Las solicitudes creadas por n8n aparecerán aquí." icon="◈" /></td></tr>
            @endforelse
        </tbody>
    </x-ui.table>
    <x-ui.pagination :paginator="$requests" scroll-to="#token-requests" />

    @if ($selected)
        <x-ui.card title="Detalle de solicitud">
            <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
                <div class="space-y-5">
                    <dl class="grid gap-3 md:grid-cols-2">
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Estado</dt><dd>{{ $selected->status->label() }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Tipo de solicitud</dt><dd>{{ $this->requestDisplayLabel($selected) }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Solicitante</dt><dd>{{ $selected->requester_name ?? $selected->telegram_username ?? 'Sin nombre' }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Aplicación</dt><dd>{{ $selected->application_name ?? $selected->requested_token_name }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Motivo</dt><dd>{{ $selected->purpose ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Fecha de solicitud</dt><dd>{{ $selected->requested_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Tipo solicitado</dt><dd>{{ $selected->requested_token_type ? strtoupper($selected->requested_token_type) : 'Sin preferencia' }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Tipo aprobado</dt><dd>{{ $selected->token_type ? strtoupper($selected->token_type) : 'Pendiente' }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Origen</dt><dd>{{ $selected->request_source }} · {{ $selected->requested_ip ?? 'IP no disponible' }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Administrador</dt><dd>{{ $selected->reviewer?->name ?? 'Pendiente' }}</dd></div>
                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Motivo rechazo</dt><dd>{{ $selected->rejection_reason ?? '—' }}</dd></div>
                    </dl>

                    <div>
                        <h3 class="font-medium">Datos seguros de origen</h3>
                        <dl class="mt-2 grid gap-2 text-sm md:grid-cols-2">
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Correo</dt><dd>{{ $selected->requester_email ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Telefono</dt><dd>{{ $selected->requester_phone ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">UUID</dt><dd class="font-mono break-all">{{ $selected->request_uuid }}</dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Entrega</dt><dd>{{ $selected->delivery_status->label() }}</dd></div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="font-medium">Historial de eventos</h3>
                        <div class="mt-2 space-y-2">
                            @foreach ($selected->events as $event)
                                <x-ui.audit-entry :entry="$event" />
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    @if ($selected->status->value === 'pending')
                        @if ($selected->requestTypeValue() === 'rotation')
                            <form wire:submit="approve" class="space-y-4">
                                <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm">Al confirmar, el token anterior dejará de funcionar inmediatamente y se generará un reemplazo con la misma fecha de caducidad.</div>
                                <dl class="grid gap-2 text-sm">
                                    <div><dt class="text-xs text-[color:var(--color-text-muted)]">Tipo</dt><dd>{{ $this->requestDisplayLabel($selected) }}</dd></div>
                                    <div><dt class="text-xs text-[color:var(--color-text-muted)]">Permisos heredados</dt><dd>{{ implode(', ', $selected->requested_abilities ?? []) }}</dd></div>
                                    <div><dt class="text-xs text-[color:var(--color-text-muted)]">Caducidad heredada</dt><dd>{{ $selected->sourceToken?->expires_at?->format('d/m/Y H:i') ?? 'Sin expiración' }}</dd></div>
                                </dl>
                                <x-ui.textarea wire:model="adminNote" label="Observación administrativa" :error="$errors->first('adminNote')" />
                                <x-ui.button type="submit" class="w-full" loading-target="approve">Aprobar rotación</x-ui.button>
                            </form>
                        @else
                        <form wire:submit="approve" class="space-y-4">
                            <x-ui.input wire:model="approvalTokenName" label="Nombre definitivo" :error="$errors->first('approvalTokenName')" />
                            <x-ui.dropdown-select wire:model="approvalUserId" label="Usuario propietario" :value="$approvalUserId" :options="$users->pluck('name', 'id')->all()" :error="$errors->first('approvalUserId')" />
                            <div class="space-y-2">
                                <x-ui.input wire:model.live="tokenExpiresInDays" type="number" min="1" max="365" step="1" label="Vigencia del token en días" :error="$errors->first('tokenExpiresInDays')" />
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($tokenExpirationQuickOptions as $days)
                                        <x-ui.button type="button" size="sm" variant="ghost" wire:click="setTokenExpiresInDays({{ $days }})">{{ $days }} {{ $days === 1 ? 'día' : 'días' }}</x-ui.button>
                                    @endforeach
                                </div>
                                <p class="text-xs text-[color:var(--color-text-muted)]">{{ $tokenExpirationPreview }}</p>
                            </div>

                            <fieldset class="space-y-3">
                                <legend class="text-sm font-medium">Tipo de token a generar</legend>
                                @foreach ($tokenTypes as $type)
                                    <x-ui.radio wire:model="approvalTokenType" name="approvalTokenType" value="{{ $type['value'] }}" label="{{ $type['label'] }}" description="{{ $type['description'] }}" />
                                    <div class="ml-7 flex flex-wrap gap-1">
                                        @foreach ($type['abilities'] as $ability)
                                            <x-ui.badge tone="info">{{ $ability }}</x-ui.badge>
                                        @endforeach
                                    </div>
                                @endforeach
                                <x-ui.form-error :message="$errors->first('approvalTokenType')" />
                            </fieldset>

                            <x-ui.textarea wire:model="adminNote" label="Observación administrativa" :error="$errors->first('adminNote')" />
                            <x-ui.button type="submit" class="w-full" loading-target="approve">Aprobar solicitud</x-ui.button>
                        </form>
                        @endif

                        <form wire:submit="reject" class="space-y-3">
                            <x-ui.textarea wire:model="rejectionReason" label="Motivo de rechazo (opcional)" :error="$errors->first('rejectionReason')" />
                            <x-ui.button type="submit" variant="danger" class="w-full" loading-target="reject">Rechazar solicitud</x-ui.button>
                        </form>
                    @else
                        <p class="text-sm text-[color:var(--color-text-muted)]">No hay acciones de aprobación disponibles para este estado.</p>
                    @endif
                </div>
            </div>
        </x-ui.card>
    @endif
</div>
