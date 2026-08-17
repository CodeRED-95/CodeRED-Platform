<div class="grid gap-6 lg:grid-cols-2">
    {{-- Aplicaciones permitidas --}}
    <x-ui.card>
        <x-ui.section-header
            title="Aplicaciones permitidas"
            description="En qué clientes de CodeRED puede iniciar sesión esta cuenta." />

        <div class="mt-[var(--space-section)] space-y-2">
            @foreach ($applications as $app)
                <div class="flex items-center justify-between gap-4 rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] px-4 py-3">
                    <div class="min-w-0">
                        <p class="type-card-title">{{ $app['label'] }}</p>
                        <p class="type-caption mt-0.5">{{ $app['description'] }}</p>

                        {{-- Un permiso que llega por el rol principal no se puede retirar desde
                             aquí: hacerlo exigiría desmontar la configuración de roles. --}}
                        @if ($app['granted'] && ! $app['revocable'])
                            <p class="mt-1 text-xs text-[color:var(--color-text-muted)]">
                                Concedido por un rol asignado; se retira editando sus roles.
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        @if ($app['granted'])
                            <x-ui.badge tone="success">Permitido</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral">Sin acceso</x-ui.badge>
                        @endif

                        @if ($canManage && ($app['revocable'] || ! $app['granted']))
                            <x-ui.button
                                size="sm"
                                :variant="$app['granted'] ? 'outline' : 'secondary'"
                                wire:click="toggleApplication('{{ $app['permission'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="toggleApplication('{{ $app['permission'] }}')"
                            >{{ $app['granted'] ? 'Retirar' : 'Conceder' }}</x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($modules !== [])
            <div class="mt-6 border-t border-[color:var(--color-border-subtle)] pt-4">
                <p class="type-caption mb-2">Módulos concedidos individualmente</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($modules as $module)
                        <x-ui.badge :tone="$module['granted'] ? 'brand' : 'neutral'">
                            {{ $module['label'] }}
                        </x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif
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

        {{-- La distinción que pediste que quedara clara en administración. --}}
        <p class="mt-4 border-t border-[color:var(--color-border-subtle)] pt-4 text-xs text-[color:var(--color-text-muted)]">
            Las sesiones de usuario son distintas de los
            <a href="{{ route('admin.api-tokens.index') }}" class="text-[color:var(--color-brand-light)] hover:text-white">tokens de API</a>,
            que sirven a integraciones y automatizaciones y no se cierran desde aquí.
        </p>
    </x-ui.card>
</div>
