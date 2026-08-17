@php
    // Aplicaciones y módulos se conceden igual y con los mismos controles; lo
    // único que cambia es qué significa cada uno, y eso lo dice la cabecera.
    $filaAcceso = function (array $acceso) use ($canManage) {
        return [
            'permission' => $acceso['permission'],
            'label' => $acceso['label'],
            'description' => $acceso['description'],
            'granted' => $acceso['granted'],
            'revocable' => $acceso['revocable'],
            'accionable' => $canManage && ($acceso['revocable'] || ! $acceso['granted']),
        ];
    };
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Accesos: dónde entra y qué consulta --}}
    <x-ui.card>
        <x-ui.section-header
            title="Accesos"
            description="Qué puede hacer esta cuenta y desde dónde. Los cambios surten efecto en la siguiente consulta, sin regenerar ningún token." />

        <div class="mt-[var(--space-section)] space-y-5">
            @foreach ([
                ['titulo' => 'Aplicaciones', 'ayuda' => 'Dónde puede iniciar sesión.', 'items' => $applications],
                ['titulo' => 'Módulos de consulta', 'ayuda' => 'Qué información puede consultar, en cualquiera de los tres clientes.', 'items' => $modules],
            ] as $grupo)
                @if ($grupo['items'] !== [])
                    <div class="space-y-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[color:var(--color-text-muted)]">{{ $grupo['titulo'] }}</p>
                            <p class="type-caption mt-1">{{ $grupo['ayuda'] }}</p>
                        </div>

                        @foreach ($grupo['items'] as $item)
                            @php $fila = $filaAcceso($item); @endphp
                            <div class="flex items-center justify-between gap-4 rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] px-4 py-3">
                                <div class="min-w-0">
                                    <p class="type-card-title">{{ $fila['label'] }}</p>
                                    <p class="type-caption mt-0.5">{{ $fila['description'] }}</p>

                                    {{-- Un permiso que llega por el rol principal no se puede retirar
                                         desde aquí: hacerlo exigiría desmontar su configuración de roles. --}}
                                    @if ($fila['granted'] && ! $fila['revocable'])
                                        <p class="mt-1 text-xs text-[color:var(--color-text-muted)]">
                                            Concedido por un rol asignado; se retira editando sus roles.
                                        </p>
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <x-ui.badge :tone="$fila['granted'] ? 'success' : 'neutral'">
                                        {{ $fila['granted'] ? 'Permitido' : 'Sin acceso' }}
                                    </x-ui.badge>

                                    @if ($fila['accionable'])
                                        <x-ui.button
                                            size="sm"
                                            :variant="$fila['granted'] ? 'outline' : 'secondary'"
                                            wire:click="toggleAccess('{{ $fila['permission'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleAccess('{{ $fila['permission'] }}')"
                                        >{{ $fila['granted'] ? 'Retirar' : 'Conceder' }}</x-ui.button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>

        <p class="mt-5 border-t border-[color:var(--color-border-subtle)] pt-4 text-xs text-[color:var(--color-text-muted)]">
            Retirar una aplicación cierra además las sesiones abiertas en ella. Retirar un módulo
            deja de autorizar esa consulta de inmediato, sin cerrar la sesión.
        </p>
    </x-ui.card>

    {{-- Sesiones activas --}}
    <x-ui.card>
        <x-ui.section-header
            title="Sesiones activas"
            description="Dónde tiene la sesión abierta esta cuenta ahora mismo.">
            @if ($canManage && $sessions->isNotEmpty())
                <x-slot:actions>
                    <x-ui.confirm-dialog
                        id="revoke-all-sessions-{{ $user->id }}"
                        title="Cerrar todas las sesiones"
                        message="La persona tendrá que volver a iniciar sesión en todos sus dispositivos. Sus tokens de API no se ven afectados."
                        confirm-label="Cerrar todas"
                        confirm-action="revokeAllSessions"
                        tone="danger">
                        <x-slot:trigger>
                            <x-ui.button variant="outline" size="sm">Cerrar todas</x-ui.button>
                        </x-slot:trigger>
                    </x-ui.confirm-dialog>
                </x-slot:actions>
            @endif
        </x-ui.section-header>

        <div class="mt-[var(--space-section)] space-y-2">
            @forelse ($sessions as $session)
                <div class="flex items-center justify-between gap-4 rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="type-card-title">{{ $session->application->label() }}</p>
                            @if ($session->client_version)
                                <x-ui.badge tone="neutral">v{{ $session->client_version }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="type-caption mt-0.5">
                            {{ $session->device_name ?? 'Dispositivo sin nombre' }}
                            @if ($session->platform) · {{ $session->platform }} @endif
                        </p>
                        <p class="mt-1 text-xs text-[color:var(--color-text-muted)]">
                            Última actividad:
                            {{ $session->last_used_at?->diffForHumans() ?? 'sin registrar' }}
                            @if ($session->ip_address) · {{ $session->ip_address }} @endif
                        </p>
                    </div>

                    @if ($canManage)
                        <x-ui.button
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            wire:click="revokeSession('{{ $session->uuid }}')"
                            wire:loading.attr="disabled"
                            wire:target="revokeSession('{{ $session->uuid }}')"
                        >Cerrar sesión</x-ui.button>
                    @endif
                </div>
            @empty
                <x-ui.empty-state
                    title="Sin sesiones activas"
                    description="Esta cuenta no tiene ninguna sesión abierta en Platform, Mobile ni Desktop."
                    icon="◌" />
            @endforelse
        </div>

        {{-- La distinción que debe quedar clara en administración. --}}
        <p class="mt-4 border-t border-[color:var(--color-border-subtle)] pt-4 text-xs text-[color:var(--color-text-muted)]">
            Las sesiones de usuario son distintas de los
            <a href="{{ route('admin.api-tokens.index') }}" class="text-[color:var(--color-brand-light)] hover:text-white">tokens de API</a>,
            que sirven a integraciones y automatizaciones y no se cierran desde aquí.
        </p>
    </x-ui.card>
</div>
