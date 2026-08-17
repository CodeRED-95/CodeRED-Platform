<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:var(--color-text-muted)]">Seguridad</p>
            <h1 class="text-2xl font-semibold tracking-tight text-[color:var(--color-text-primary)]">Solicitudes de acceso</h1>
            <p class="max-w-2xl text-sm text-[color:var(--color-text-muted)]">
                Accesos a módulos de consulta pedidos por usuarios desde CodeRED Desktop o Mobile.
                Al aprobar, el permiso queda concedido de inmediato: no hace falta que vuelvan a iniciar sesión.
            </p>
        </div>

        @if ($pendientes > 0)
            <x-ui.badge tone="warning" class="self-start lg:self-auto">
                {{ $pendientes }} {{ $pendientes === 1 ? 'pendiente' : 'pendientes' }}
            </x-ui.badge>
        @endif
    </div>

    <section class="rounded-xl border border-white/10 bg-white/[0.035] p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,24rem)_minmax(0,14rem)]">
            <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="Nombre o correo del solicitante..." />
            <x-ui.dropdown-select
                id="permission-request-status"
                wire:model.live="status"
                label="Estado"
                :value="$status"
                :options="['all' => 'Todos'] + collect($estados)->mapWithKeys(fn ($estado) => [$estado->value => $estado->label()])->all()" />
        </div>
    </section>

    @if ($solicitudes->isEmpty())
        <x-ui.empty-state
            title="No hay solicitudes"
            description="{{ $status === 'pending' ? 'No queda ninguna solicitud pendiente por revisar.' : 'Ninguna solicitud coincide con los filtros aplicados.' }}" />
    @else
        <x-ui.table caption="Solicitudes de acceso a módulos de consulta">
            <thead class="border-b border-white/10 bg-white/[0.03] text-xs uppercase tracking-wide text-[color:var(--color-text-muted)]">
                <tr>
                    <th class="px-4 py-3">Solicitante</th>
                    <th class="px-4 py-3">Acceso</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Solicitada</th>
                    <th class="px-4 py-3">Resolución</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @foreach ($solicitudes as $solicitud)
                    @php
                        $tono = match ($solicitud->status) {
                            \App\Enums\PermissionRequestStatus::Pending => 'warning',
                            \App\Enums\PermissionRequestStatus::Approved => 'success',
                            \App\Enums\PermissionRequestStatus::Rejected => 'danger',
                            default => 'neutral',
                        };
                    @endphp
                    <tr wire:key="permission-request-{{ $solicitud->id }}" class="align-top">
                        <td class="px-4 py-3">
                            <p class="font-medium text-[color:var(--color-text-primary)]">{{ $solicitud->user?->name ?? 'Usuario eliminado' }}</p>
                            <p class="text-xs text-[color:var(--color-text-muted)]">{{ $solicitud->user?->email }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-[color:var(--color-text-primary)]">{{ $solicitud->accessLabel() }}</p>
                            <p class="font-mono text-xs text-[color:var(--color-text-muted)]">{{ $solicitud->permission }}</p>
                            @if ($solicitud->reason)
                                {{-- Justificación de quien pide: es lo que hay que leer para decidir. --}}
                                <p class="mt-1 max-w-xs text-xs text-[color:var(--color-text-secondary)]">{{ $solicitud->reason }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge :tone="$tono">{{ $solicitud->status->label() }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-[color:var(--color-text-muted)]">
                            {{ $solicitud->requested_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-[color:var(--color-text-muted)]">
                            @if ($solicitud->status->isOpen())
                                <span aria-hidden="true">—</span>
                                <span class="sr-only">Sin resolver</span>
                            @else
                                <p>{{ $solicitud->reviewer?->name ?? 'Sistema' }}</p>
                                <p>{{ $solicitud->reviewed_at?->format('d/m/Y H:i') }}</p>
                                @if ($solicitud->rejection_reason)
                                    <p class="mt-1 text-[color:var(--color-text-secondary)]">{{ $solicitud->rejection_reason }}</p>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($solicitud->status->isOpen() && $canManage)
                                <div class="flex justify-end gap-2">
                                    <x-ui.button
                                        type="button"
                                        size="sm"
                                        variant="primary"
                                        wire:click="approve({{ $solicitud->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="approve({{ $solicitud->id }})">
                                        Aprobar
                                    </x-ui.button>
                                    <x-ui.button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        wire:click="confirmReject({{ $solicitud->id }})">
                                        Rechazar
                                    </x-ui.button>
                                </div>
                            @elseif ($solicitud->status->isOpen())
                                {{-- Ver la bandeja y decidir son permisos distintos. --}}
                                <p class="text-right text-xs text-[color:var(--color-text-muted)]">Sin permiso para decidir</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>

        <x-ui.pagination :paginator="$solicitudes" />
    @endif

    {{-- El rechazo pide motivo: quien lo recibe merece saber por qué. --}}
    @if ($rejectingId !== null)
        <x-ui.modal
            :open="true"
            title="Rechazar solicitud"
            wire:key="reject-{{ $rejectingId }}"
            x-init="$watch('open', (abierto) => { if (! abierto) { $wire.cancelReject() } })">
            <div class="space-y-4">
                <p class="text-sm text-[color:var(--color-text-muted)]">
                    El motivo queda registrado en la solicitud. Es opcional, pero ayuda a quien la pidió a entender qué hacer después.
                </p>
                <x-ui.textarea
                    id="permission-request-reason"
                    wire:model="rejectionReason"
                    label="Motivo"
                    rows="3"
                    maxlength="500"
                    placeholder="Por ejemplo: el acceso a RUC se concede sólo al equipo de cobranzas." />
                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="ghost" wire:click="cancelReject">Cancelar</x-ui.button>
                    <x-ui.button type="button" variant="danger" wire:click="reject" wire:loading.attr="disabled" wire:target="reject">
                        Rechazar solicitud
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
