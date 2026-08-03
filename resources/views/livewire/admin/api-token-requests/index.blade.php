<div class="token-requests-dashboard space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:var(--color-text-muted)]">CodeRED Platform</p>
            <h1 class="text-2xl font-semibold tracking-tight text-[color:var(--color-text-primary)]">Solicitudes de tokens</h1>
            <p class="max-w-2xl text-sm text-[color:var(--color-text-muted)]">Revisión y entrega segura de tokens solicitados por integraciones.</p>
        </div>
        <a href="{{ url('/solicitar-token') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[color:var(--color-danger)] px-4 text-sm font-semibold text-white shadow-lg shadow-red-950/30 transition hover:brightness-110 focus-ring">
            + Nueva solicitud
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_19rem]">
        <main class="space-y-4">
            <section class="grid gap-3 md:grid-cols-5" aria-label="Resumen de solicitudes">
                @php
                    $cards = [
                        ['label' => 'Pendientes', 'value' => $summary['pending'], 'icon' => '◷', 'tone' => 'text-blue-300 bg-blue-500/10 border-blue-500/20'],
                        ['label' => 'Aprobadas hoy', 'value' => $summary['approved_today'], 'icon' => '✓', 'tone' => 'text-emerald-300 bg-emerald-500/10 border-emerald-500/20'],
                        ['label' => 'Rechazadas hoy', 'value' => $summary['rejected_today'], 'icon' => '×', 'tone' => 'text-rose-300 bg-rose-500/10 border-rose-500/20'],
                        ['label' => 'Entregadas', 'value' => $summary['delivered'], 'icon' => '➤', 'tone' => 'text-sky-300 bg-sky-500/10 border-sky-500/20'],
                        ['label' => 'Vencidas', 'value' => $summary['expired'], 'icon' => '◌', 'tone' => 'text-violet-300 bg-violet-500/10 border-violet-500/20'],
                    ];
                @endphp
                @foreach ($cards as $card)
                    <article class="rounded-xl border border-white/10 bg-white/[0.035] p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 place-items-center rounded-xl border {{ $card['tone'] }} text-xl" aria-hidden="true">{{ $card['icon'] }}</span>
                            <div>
                                <p class="text-xs text-[color:var(--color-text-muted)]">{{ $card['label'] }}</p>
                                <p class="mt-1 text-2xl font-semibold leading-none">{{ $card['value'] }}</p>
                                <p class="mt-1 text-xs text-[color:var(--color-text-muted)]">Solicitudes</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="rounded-xl border border-white/10 bg-white/[0.035] p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">Filtros</h2>
                    <x-ui.button type="button" size="sm" variant="ghost" wire:click="clearFilters">Limpiar filtros</x-ui.button>
                </div>
                <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="Solicitante, app o UUID..." />
                    <x-ui.dropdown-select id="request-status" wire:model.live="status" label="Estado" :value="$status" :options="['' => 'Todos'] + collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
                    <x-ui.dropdown-select id="delivery-status" wire:model.live="deliveryStatus" label="Entrega" :value="$deliveryStatus" :options="['' => 'Todas'] + collect($deliveryStatuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
                    <x-ui.dropdown-select id="request-ability" wire:model.live="ability" label="Scope" :value="$ability" :options="['' => 'Todos'] + $availableAbilities" />
                    <x-ui.dropdown-select id="request-reviewer" wire:model.live="reviewerId" label="Revisor" :value="$reviewerId" :options="[0 => 'Todos'] + $reviewers->pluck('name', 'id')->all()" />
                    <x-ui.input id="request-date" wire:model.live="date" type="date" label="Fecha" />
                </div>
            </section>

            <section class="token-requests-table overflow-hidden rounded-xl border border-white/10 bg-white/[0.035] shadow-sm" id="token-requests">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-white/10 bg-white/[0.03] text-xs uppercase tracking-wide text-[color:var(--color-text-muted)]">
                            <tr>
                                <th class="px-4 py-3">Solicitud</th>
                                <th class="px-4 py-3">Solicitante</th>
                                <th class="px-4 py-3">Aplicación</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse ($requests as $request)
                                @php
                                    $maskedContact = $request->maskedDeliveryContact();
                                    $trackingCode = $request->metadata['tracking_code'] ?? ('CR-'.str_pad((string) $request->id, 4, '0', STR_PAD_LEFT));
                                    $contact = $maskedContact['email'] ?? $maskedContact['telegram'] ?? $maskedContact['whatsapp'] ?? 'Contacto protegido';
                                @endphp
                                <tr class="align-top transition hover:bg-white/[0.03]">
                                    <td class="px-4 py-3">
                                        <button type="button" class="font-mono text-xs font-semibold text-sky-300 underline-offset-4 hover:underline" wire:click="selectRequest({{ $request->id }})">{{ $trackingCode }}</button>
                                        <p class="mt-1 max-w-[12rem] truncate font-mono text-[11px] text-[color:var(--color-text-muted)]">{{ $request->request_uuid }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ $request->requester_name ?? 'Solicitante sin nombre' }}</p>
                                        <p class="text-xs text-[color:var(--color-text-muted)]">{{ $request->delivery_channel ? ucfirst($request->delivery_channel).' · ' : 'Manual · ' }}{{ $contact }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold">{{ $request->application_name ?? $request->requested_token_name }}</p>
                                        <p class="max-w-[16rem] text-xs text-[color:var(--color-text-muted)]">{{ $request->purpose ?? 'Sin motivo registrado' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-sm">{{ $this->requestDisplayLabel($request) }}</p>
                                        <x-ui.badge tone="info">{{ $request->requested_token_type ? strtoupper($request->requested_token_type) : 'SIN PREFERENCIA' }}</x-ui.badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-ui.badge>{{ $request->status->label() }}</x-ui.badge>
                                        <div class="mt-1"><x-ui.badge tone="info">{{ $request->delivery_status->label() }}</x-ui.badge></div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-[color:var(--color-text-muted)]">{{ $request->requested_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-white/10 bg-white/[0.04] text-sm hover:border-sky-400/60" wire:click="selectRequest({{ $request->id }})" title="Ver detalles" aria-label="Ver detalles">◉</button>
                                            @if ($request->status->value === 'pending')
                                                <button type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-white/10 bg-white/[0.04] text-sm hover:border-amber-400/60" wire:click="cancel({{ $request->id }})" title="Cancelar" aria-label="Cancelar solicitud">✎</button>
                                            @endif
                                            @can('api-token-requests.delete')
                                                @if (! $request->isDelivered())
                                                    <button type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-red-500/30 bg-red-500/10 text-sm text-red-300 hover:border-red-400" wire:click="confirmDeleteRequest({{ $request->id }})" title="Eliminar" aria-label="Eliminar solicitud">⌫</button>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="p-8"><x-ui.empty-state title="No hay solicitudes" description="Las solicitudes creadas por n8n o el formulario público aparecerán aquí." icon="◈" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-white/10 px-4 py-3">
                    <x-ui.pagination :paginator="$requests" scroll-to="#token-requests" />
                </div>
            </section>
        </main>

        <aside class="token-requests-aside space-y-5 rounded-xl border border-white/10 bg-white/[0.035] p-5 shadow-sm">
            <section class="space-y-3">
                <h2 class="font-semibold">Información</h2>
                <div class="mx-auto grid h-20 w-20 place-items-center rounded-2xl border border-blue-500/20 bg-blue-500/10 text-4xl text-blue-300" aria-hidden="true">▣</div>
                <h3 class="font-semibold">Seguridad ante todo</h3>
                <p class="text-sm leading-6 text-[color:var(--color-text-muted)]">Las solicitudes son revisadas manualmente antes de entregar cualquier token. Verifica siempre la información del solicitante.</p>
            </section>
            <section class="space-y-3 border-t border-white/10 pt-4">
                <h3 class="font-semibold">Estados</h3>
                <dl class="space-y-3 text-sm">
                    <div><dt class="font-semibold text-amber-300">● Pendiente</dt><dd class="text-[color:var(--color-text-muted)]">En espera de revisión</dd></div>
                    <div><dt class="font-semibold text-emerald-300">● Aprobada</dt><dd class="text-[color:var(--color-text-muted)]">Solicitud aprobada</dd></div>
                    <div><dt class="font-semibold text-sky-300">● Entregada</dt><dd class="text-[color:var(--color-text-muted)]">Token entregado al solicitante</dd></div>
                    <div><dt class="font-semibold text-rose-300">● Rechazada</dt><dd class="text-[color:var(--color-text-muted)]">Solicitud rechazada</dd></div>
                    <div><dt class="font-semibold text-violet-300">● Vencida</dt><dd class="text-[color:var(--color-text-muted)]">Solicitud expirada</dd></div>
                </dl>
            </section>
            <section class="space-y-3 border-t border-white/10 pt-4">
                <h3 class="font-semibold">¿Necesitas ayuda?</h3>
                <p class="text-sm text-[color:var(--color-text-muted)]">Consulta nuestra documentación o contacta al equipo de soporte.</p>
                <a href="{{ url('/docs') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-9 items-center rounded-lg border border-white/10 px-3 text-sm font-semibold hover:border-sky-400/60 focus-ring">Ver documentación ↗</a>
            </section>
        </aside>
    </div>

    @if ($selected)
        @php
            $selectedMaskedContact = $selected->maskedDeliveryContact();
            $selectedTrackingCode = $selected->metadata['tracking_code'] ?? ('CR-'.str_pad((string) $selected->id, 4, '0', STR_PAD_LEFT));
        @endphp
        <div class="token-request-detail-modal fixed inset-0 z-50 grid place-items-center bg-black/65 p-4" role="dialog" aria-modal="true" aria-labelledby="token-request-detail-title">
            <section class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-2xl border border-white/10 bg-[color:var(--color-surface)] shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-white/10 p-5">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 id="token-request-detail-title" class="text-xl font-semibold">Detalles de la solicitud</h2><span class="sr-only">Detalle de solicitud</span>
                            <x-ui.badge>{{ $selected->status->label() }}</x-ui.badge>
                        </div>
                        <p class="mt-3 font-mono text-sm font-semibold">{{ $selectedTrackingCode }}</p>
                        <p class="mt-1 font-mono text-xs text-[color:var(--color-text-muted)]">ID: {{ $selected->request_uuid }}</p>
                    </div>
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-white/10 hover:border-red-400/70" wire:click="closeSelectedRequest" aria-label="Cerrar">×</button>
                </header>

                <div class="grid max-h-[calc(90vh-6rem)] overflow-y-auto lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <div class="space-y-5 border-r border-white/10 p-5">
                        <dl class="grid gap-4 rounded-xl border border-white/10 bg-white/[0.03] p-4 md:grid-cols-3">
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Solicitante</dt><dd class="font-semibold">{{ $selected->requester_name ?? $selected->telegram_username ?? 'Sin nombre' }}</dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Aplicación</dt><dd class="font-semibold">{{ $selected->application_name ?? $selected->requested_token_name }}</dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Tipo</dt><dd class="font-semibold">{{ $this->requestDisplayLabel($selected) }}</dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Scope</dt><dd><x-ui.badge tone="info">{{ $selected->requested_token_type ? strtoupper($selected->requested_token_type) : 'SIN PREFERENCIA' }}</x-ui.badge></dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Fecha de solicitud</dt><dd>{{ $selected->requested_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Revisor asignado</dt><dd>{{ $selected->reviewer?->name ?? '—' }}</dd></div>
                        </dl>

                        <section class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                            <div class="mb-3 flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-medium">Datos para entrega</h3>
                                    <p class="mt-1 text-sm text-[color:var(--color-text-muted)]">{{ $selected->isDelivered() ? 'Los datos completos fueron eliminados después de confirmar la entrega.' : 'Datos sensibles protegidos. Revelar solo cuando se vaya a realizar la entrega.' }}</p>
                                </div>
                                <x-ui.badge tone="info">{{ $selected->delivery_status->label() }}</x-ui.badge>
                            </div>
                            @if ($selected->isDelivered())
                                <dl class="grid gap-2 text-sm md:grid-cols-2">
                                    @if ($selectedMaskedContact['email'])<div><dt class="text-xs text-[color:var(--color-text-muted)]">Correo</dt><dd>{{ $selectedMaskedContact['email'] }}</dd></div>@endif
                                    @if ($selectedMaskedContact['telegram'])<div><dt class="text-xs text-[color:var(--color-text-muted)]">Telegram</dt><dd>{{ $selectedMaskedContact['telegram'] }}</dd></div>@endif
                                    @if ($selectedMaskedContact['whatsapp'])<div><dt class="text-xs text-[color:var(--color-text-muted)]">WhatsApp</dt><dd>{{ $selectedMaskedContact['whatsapp'] }}</dd></div>@endif
                                    <div><dt class="text-xs text-[color:var(--color-text-muted)]">Entregado por</dt><dd>{{ $selected->deliveredBy?->name ?? 'Sistema externo' }}</dd></div>
                                    <div><dt class="text-xs text-[color:var(--color-text-muted)]">Fecha de entrega</dt><dd>{{ $selected->delivered_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                                </dl>
                            @elseif (! $deliveryContactRevealed)
                                @can('api-token-requests.view-delivery-contact')
                                    <x-ui.button type="button" size="sm" wire:click="revealDeliveryContact" loading-target="revealDeliveryContact">Mostrar datos de entrega</x-ui.button>
                                @else
                                    <p class="text-sm text-[color:var(--color-text-muted)]">No tienes permiso para revelar los datos completos de entrega.</p>
                                @endcan
                                <dl class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                                    @if ($selectedMaskedContact['email'])<div><dt class="text-xs text-[color:var(--color-text-muted)]">Correo</dt><dd>{{ $selectedMaskedContact['email'] }}</dd></div>@endif
                                    @if ($selectedMaskedContact['telegram'])<div><dt class="text-xs text-[color:var(--color-text-muted)]">Telegram</dt><dd>{{ $selectedMaskedContact['telegram'] }}</dd></div>@endif
                                    @if ($selectedMaskedContact['whatsapp'])<div><dt class="text-xs text-[color:var(--color-text-muted)]">WhatsApp</dt><dd>{{ $selectedMaskedContact['whatsapp'] }}</dd></div>@endif
                                </dl>
                            @else
                                <dl class="grid gap-3 text-sm">
                                    @if ($revealedDeliveryContact['email'])<div class="rounded-lg border border-white/10 p-3"><dt class="text-xs text-[color:var(--color-text-muted)]">Correo</dt><dd class="break-all">{{ $revealedDeliveryContact['email'] }}</dd></div>@endif
                                    @if ($revealedDeliveryContact['telegram'])<div class="rounded-lg border border-white/10 p-3"><dt class="text-xs text-[color:var(--color-text-muted)]">Telegram</dt><dd>{{ $revealedDeliveryContact['telegram'] }}</dd></div>@endif
                                    @if ($revealedDeliveryContact['whatsapp'])<div class="rounded-lg border border-white/10 p-3"><dt class="text-xs text-[color:var(--color-text-muted)]">WhatsApp</dt><dd>{{ $revealedDeliveryContact['whatsapp'] }}</dd></div>@endif
                                </dl>
                            @endif
                        </section>

                        <section class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                            <h3 class="font-medium">Notificaciones</h3>
                            <div class="mt-3 space-y-2 text-sm">
                                @forelse ($selected->webhookDeliveries->where('event_type', 'token_request.created') as $delivery)
                                    <div class="rounded-lg border border-white/10 p-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-medium">Telegram {{ $delivery->delivered_at ? 'notificado' : 'pendiente de notificación' }}</span><x-ui.badge tone="info">{{ ucfirst($delivery->status) }}</x-ui.badge></div>
                                        <p class="mt-2 text-xs text-[color:var(--color-text-muted)]">Intentos: {{ $delivery->attempts }} · Resultado: {{ $delivery->last_status_code ?? $delivery->last_error ?? 'Sin respuesta' }}</p>
                                    </div>
                                @empty
                                    <p class="text-sm text-[color:var(--color-text-muted)]">Telegram todavía no registra notificación para esta solicitud.</p>
                                @endforelse
                            </div>
                            @can('api-token-requests.retry-notification')
                                <div class="mt-3"><x-ui.button type="button" size="sm" variant="secondary" wire:click="retryNotification({{ $selected->id }})" loading-target="retryNotification">Reintentar notificación</x-ui.button></div>
                            @endcan
                        </section>
                    </div>

                    <div class="space-y-5 p-5">
                        <div class="grid grid-cols-2 border-b border-white/10 text-sm font-semibold">
                            <span class="border-b-2 border-[color:var(--color-danger)] px-3 py-2 text-[color:var(--color-danger)]">Información</span>
                            <span class="px-3 py-2 text-[color:var(--color-text-muted)]">Historial</span>
                        </div>
                        <dl class="grid gap-3 text-sm md:grid-cols-2">
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Estado actual</dt><dd class="font-semibold">{{ $selected->status->label() }}</dd></div>
                            <div><dt class="text-xs text-[color:var(--color-text-muted)]">Última actualización</dt><dd>{{ $selected->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                            <div class="md:col-span-2"><dt class="text-xs text-[color:var(--color-text-muted)]">Notas del solicitante</dt><dd>{{ $selected->purpose ?? '—' }}</dd></div>
                            <div class="md:col-span-2"><dt class="text-xs text-[color:var(--color-text-muted)]">Información adicional</dt><dd>{{ $selected->application_name ? 'Solicitud pública desde '.$selected->application_name.'.' : '—' }}</dd></div>
                        </dl>

                        @if ($selected->events->isNotEmpty())
                            <section>
                                <h3 class="font-medium">Historial de eventos</h3>
                                <div class="mt-2 max-h-44 space-y-2 overflow-y-auto pr-1">
                                    @foreach ($selected->events as $event)
                                        <x-ui.audit-entry :entry="$event" />
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if ($selected->status->value === 'pending')
                            @if ($selected->requestTypeValue() === 'rotation')
                                <form wire:submit="approve" class="space-y-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-100">Al confirmar, el token anterior dejará de funcionar inmediatamente y se generará un reemplazo con la misma fecha de caducidad.</div>
                                    <dl class="grid gap-2 text-sm">
                                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Tipo</dt><dd>{{ $this->requestDisplayLabel($selected) }}</dd></div>
                                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Permisos heredados</dt><dd>{{ implode(', ', $selected->requested_abilities ?? []) }}</dd></div>
                                        <div><dt class="text-xs text-[color:var(--color-text-muted)]">Caducidad heredada</dt><dd>{{ $selected->sourceToken?->expires_at?->format('d/m/Y H:i') ?? 'Sin expiración' }}</dd></div>
                                    </dl>
                                    <x-ui.textarea wire:model="adminNote" label="Observación administrativa" :error="$errors->first('adminNote')" />
                                    <x-ui.button type="submit" class="w-full" loading-target="approve">Aprobar rotación</x-ui.button>
                                </form>
                            @else
                                <form wire:submit="approve" class="space-y-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                    <x-ui.input wire:model="approvalTokenName" label="Nombre definitivo" :error="$errors->first('approvalTokenName')" />
                                    <x-ui.dropdown-select wire:model="approvalUserId" label="Usuario propietario" :value="$approvalUserId" :options="$users->pluck('name', 'id')->all()" :error="$errors->first('approvalUserId')" />
                                    <x-ui.input wire:model.live="tokenExpiresInDays" type="number" min="1" max="365" step="1" label="Vigencia del token en días" :error="$errors->first('tokenExpiresInDays')" />
                                    <fieldset class="space-y-2"><legend class="text-sm font-medium">Tipo de token</legend>@foreach ($tokenTypes as $type)<x-ui.radio wire:model="approvalTokenType" name="approvalTokenType" value="{{ $type['value'] }}" label="{{ $type['label'] }}" description="{{ $type['description'] }}" />@endforeach<x-ui.form-error :message="$errors->first('approvalTokenType')" /></fieldset>
                                    <x-ui.textarea wire:model="adminNote" label="Observación administrativa" :error="$errors->first('adminNote')" />
                                    <x-ui.button type="submit" class="w-full" loading-target="approve">Aprobar solicitud</x-ui.button>
                                </form>
                            @endif
                            <form wire:submit="reject" class="space-y-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                <x-ui.textarea wire:model="rejectionReason" label="Motivo de rechazo (opcional)" :error="$errors->first('rejectionReason')" />
                                <x-ui.button type="submit" variant="danger" class="w-full" loading-target="reject">Rechazar solicitud</x-ui.button>
                            </form>
                        @elseif ($selected->status->value === 'approved' && ! $selected->isDelivered())
                            @if ($confirmingDelivery)
                                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100"><p class="font-semibold">Confirmar entrega</p><p class="mt-2">Al marcar esta solicitud como entregada, los datos completos dejarán de estar disponibles. Esta acción no se puede deshacer.</p><div class="mt-4 flex gap-2"><x-ui.button type="button" variant="ghost" wire:click="cancelDeliveryConfirmation">Cancelar</x-ui.button><x-ui.button type="button" variant="danger" wire:click="markSelectedAsDelivered" loading-target="markSelectedAsDelivered">Confirmar entrega</x-ui.button></div></div>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.button type="button" wire:click="confirmDelivery">Marcar como entregado</x-ui.button>
                                    @can('api-token-requests.reveal_token')
                                        @if ($selected->status === \App\Enums\ApiTokenRequestStatus::Approved && !$selected->token_revealed_at)
                                            <x-ui.button type="button" variant="warning" wire:click="$set('confirmingManualReveal', true)">Revelar token</x-ui.button>
                                        @endif
                                    @endcan
                                </div>
                            @endif
                        @else
                            <p class="text-sm text-[color:var(--color-text-muted)]">No hay acciones de aprobación disponibles para este estado.</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    @endif

    @if ($deleteRequestId)
        @php($deleteRequest = \App\Models\ApiTokenRequest::query()->find($deleteRequestId))
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="delete-token-request-title">
            <section class="w-full max-w-md rounded-2xl border border-white/10 bg-[color:var(--color-surface)] p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex gap-4">
                        <span class="grid h-14 w-14 place-items-center rounded-full border border-orange-500/30 bg-orange-500/10 text-3xl text-orange-300" aria-hidden="true">⚠</span>
                        <div>
                            <h2 id="delete-token-request-title" class="text-lg font-semibold">¿Eliminar solicitud?</h2>
                            <p class="mt-3 text-sm leading-6 text-[color:var(--color-text-muted)]">Esta acción no se puede deshacer. ¿Estás seguro de que deseas eliminar la solicitud <strong>{{ $deleteRequest?->metadata['tracking_code'] ?? ('CR-'.str_pad((string) $deleteRequestId, 4, '0', STR_PAD_LEFT)) }}</strong>?</p>
                        </div>
                    </div>
                    <button type="button" wire:click="cancelDeleteRequest" aria-label="Cerrar" class="text-xl text-[color:var(--color-text-muted)]">×</button>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <x-ui.button type="button" variant="ghost" wire:click="cancelDeleteRequest">Cancelar</x-ui.button>
                    <x-ui.button type="button" variant="danger" wire:click="deleteConfirmedRequest" loading-target="deleteConfirmedRequest">Eliminar</x-ui.button>
                </div>
                <p class="mt-4 text-xs font-semibold text-red-300">No se permite eliminar una solicitud entregada.</p>
            </section>
        </div>
    @endif
    
    @if($confirmingManualReveal)
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="manual-reveal-title">
            <section class="w-full max-w-lg rounded-2xl border border-white/10 bg-[color:var(--color-surface)] p-6 shadow-2xl">
                <h2 id="manual-reveal-title" class="text-lg font-semibold">Confirmar entrega manual</h2>
                <p class="mt-2 text-sm text-[color:var(--color-text-muted)]">Estás a punto de revelar el token completo. Esta acción quedará registrada y el token no podrá volver a mostrarse. Confirma que vas a realizar la entrega manual al solicitante.</p>
                
                <div class="mt-4 space-y-4">
                    <x-ui.textarea wire:model.defer="manualDeliveryReason" label="Motivo de la revelación" placeholder="Ej: Entrega en persona al solicitante." required />
                    <x-ui.dropdown-select wire:model.defer="manualDeliveryMethod" label="Método de entrega" :options="['presencial' => 'Presencial', 'llamada' => 'Llamada', 'canal_corporativo' => 'Canal corporativo', 'otro' => 'Otro']" required />
                    <label class="flex gap-3 rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-[color:var(--color-text-secondary)]">
                        <input type="checkbox" wire:model.defer="manualDeliveryConfirmation" class="mt-1">
                        <span>Confirmo que entregaré este token al solicitante autorizado.</span>
                    </label>
                    <x-ui.form-error :message="$errors->first('manualDeliveryConfirmation')" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-ui.button variant="secondary" wire:click="$set('confirmingManualReveal', false)">Cancelar</x-ui.button>
                    <x-ui.button variant="warning" wire:click="confirmManualReveal" loading-target="confirmManualReveal">Revelar y marcar como entregado</x-ui.button>
                </div>
            </section>
        </div>
    @endif

    @if($revealedToken)
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/80 p-4" role="dialog" aria-modal="true" aria-labelledby="revealed-token-title">
            <section class="w-full max-w-lg rounded-2xl border border-amber-500/30 bg-[color:var(--color-surface)] p-6 shadow-2xl" x-data="{
                token: @js($revealedToken),
                copied: false,
                copyToClipboard() {
                    navigator.clipboard.writeText(this.token).then(() => {
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    });
                }
            }">
                <h2 id="revealed-token-title" class="text-lg font-semibold text-amber-200">Token Revelado</h2>
                <p class="mt-2 text-sm text-amber-100/80">Este token se muestra <strong>una sola vez</strong>. Cópialo y entrégalo de forma segura al solicitante. Al cerrar esta ventana, el token no podrá recuperarse.</p>
                
                <div class="relative mt-4">
                    <input type="text" :value="token" readonly class="w-full rounded-lg border-white/20 bg-white/10 pr-12 font-mono text-sm text-white">
                    <button @click="copyToClipboard()" class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-lg hover:bg-white/20" :class="copied ? 'text-emerald-400' : ''">
                        <span x-show="!copied" class="text-lg">📋</span>
                        <span x-show="copied" class="text-lg">✓</span>
                    </button>
                </div>

                <div class="mt-6 text-right">
                    <x-ui.button variant="danger" wire:click="closeRevealModal">He copiado el token, cerrar</x-ui.button>
                </div>
            </section>
        </div>
    @endif
</div>
