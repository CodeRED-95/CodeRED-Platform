<div class="space-y-6">
    <x-ui.page-header
        title="Integraciones n8n"
        subtitle="Administración visual de pairing, discovery, heartbeat y capacidades para múltiples instancias de n8n."
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <x-ui.button href="{{ route('api.docs') }}" variant="outline" size="sm">Documentación</x-ui.button>
                <x-ui.button wire:click="connect" loading-target="connect">Conectar con n8n</x-ui.button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if($pairing)
        <x-ui.alert tone="warning" title="Código de pairing activo">
            <div class="mt-3 grid gap-3 md:grid-cols-3">
                <div>
                    <p class="text-xs text-[color:var(--color-text-muted)]">Código</p>
                    <p class="font-mono text-2xl font-semibold">{{ $pairing->pair_code }}</p>
                </div>
                <div>
                    <p class="text-xs text-[color:var(--color-text-muted)]">Duración</p>
                    <p>{{ $pairing->expires_at?->diffForHumans() }}</p>
                </div>
                <div>
                    <p class="text-xs text-[color:var(--color-text-muted)]">Estado</p>
                    <x-ui.badge tone="warning">Pendiente</x-ui.badge>
                </div>
            </div>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
        @forelse($integrations as $integration)
            @php($connectionStatus = $integration->connectionStatus())
            @php($statusTone = match ($connectionStatus) { 'connected' => 'success', 'degraded' => 'warning', 'connecting' => 'warning', 'waiting_heartbeat' => 'warning', 'unpaired' => 'neutral', 'revoked' => 'danger', default => 'danger' })
            @php($heartbeatLabel = $integration->last_seen_at ? $integration->last_seen_at->diffForHumans() : 'sin confirmar')
            @php($discoveryLabel = $integration->logs->firstWhere('event', 'Discovery')?->created_at?->diffForHumans() ?? 'sin registro')
            @php($visibleCapabilities = $integration->capabilities->take(4))
            @php($capabilitiesTotal = $integration->capabilities->count())
            @php($servicesSummary = $integration->services->groupBy('service'))

            <x-ui.card class="xl:col-span-2" padding="p-0" variant="elevated">
                <div class="flex flex-col gap-5 border-b border-[color:var(--color-border-subtle)] p-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-1 items-start gap-4">
                        <div class="flex size-14 items-center justify-center rounded-2xl border border-[color:var(--color-border-subtle)] bg-[radial-gradient(circle_at_top,_rgba(255,255,255,.14),_rgba(37,99,235,.10))] text-lg font-black tracking-tight text-white shadow-[var(--shadow-sm)]">
                            n8n
                        </div>
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-xl font-semibold">{{ $integration->instance_name }}</h2>
                                <x-ui.badge :tone="$statusTone">{{ $integration->connectionLabel() }}</x-ui.badge>
                                <x-ui.badge tone="info">{{ $integration->version ?? 'Sin versión' }}</x-ui.badge>
                                <x-ui.badge tone="brand">{{ $integration->environment ?? 'sin entorno' }}</x-ui.badge>
                            </div>
                            <p class="text-sm text-[color:var(--color-text-muted)]">
                                URL: <span class="font-medium text-[color:var(--color-text-primary)]">{{ $integration->instance_url ?? 'sin URL' }}</span>
                                · Protocolo: <span class="font-medium text-[color:var(--color-text-primary)]">{{ $integration->protocol_version ?? '1.0' }}</span>
                            </p>
                            <div class="flex flex-wrap gap-2 text-xs text-[color:var(--color-text-muted)]">
                                <span class="rounded-full bg-white/5 px-2.5 py-1">UUID: <span class="font-mono text-[color:var(--color-text-primary)]">{{ $integration->integration_uuid }}</span></span>
                                <span class="rounded-full bg-white/5 px-2.5 py-1">Host: <span class="font-medium text-[color:var(--color-text-primary)]">{{ $integration->hostname ?? 'sin host' }}</span></span>
                                <span class="rounded-full bg-white/5 px-2.5 py-1">Estado: <span class="font-medium text-[color:var(--color-text-primary)]">{{ $connectionStatus }}</span></span>
                                <span class="rounded-full bg-white/5 px-2.5 py-1">Último heartbeat: <span class="font-medium text-[color:var(--color-text-primary)]">{{ $heartbeatLabel }}</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="sm" variant="secondary" wire:click="testConnection({{ $integration->id }})">Probar conexión</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="reconnect({{ $integration->id }})">Reconectar</x-ui.button>
                        <x-ui.button size="sm" variant="danger" wire:click="rotateSecret({{ $integration->id }})">Regenerar secreto</x-ui.button>
                    </div>
                </div>

                <div class="grid gap-3 border-b border-[color:var(--color-border-subtle)] p-5 sm:grid-cols-2 xl:grid-cols-5">
                    <x-ui.stat-card label="Último heartbeat" :value="$heartbeatLabel" tone="info" description="Tiempo relativo de la última señal recibida" />
                    <x-ui.stat-card label="Último discovery" :value="$discoveryLabel" tone="brand" description="Última publicación de capacidades" />
                    <x-ui.stat-card label="Plugins" :value="$integration->plugins_count" tone="ivory" description="Registrados en la instancia" />
                    <x-ui.stat-card label="Capabilities" :value="$capabilitiesTotal" tone="success" description="Capacidades publicadas" />
                    <x-ui.stat-card label="Latency" :value="$integration->latency_ms ?? 0" tone="warning" description="ms" />
                </div>

                @if($lastTestIntegrationId === $integration->id && $lastTestResult)
                    <div class="border-b border-[color:var(--color-border-subtle)] p-5">
                        <x-ui.alert :tone="$lastTestResult['ok'] ? 'success' : 'warning'" title="Resultado de prueba">
                            <p>Tiempo: {{ $lastTestResult['latency_ms'] ?? '—' }} ms · Resultado: {{ $lastTestResult['message'] }}</p>
                        </x-ui.alert>
                    </div>
                @endif

                <div class="grid gap-4 p-5 lg:grid-cols-2 2xl:grid-cols-4">
                    <x-ui.card variant="metric" padding="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-[color:var(--color-text-secondary)]">Servicios</p>
                                <p class="mt-1 text-lg font-semibold">agent / n8n</p>
                            </div>
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-xs text-[color:var(--color-text-secondary)]">2 servicios</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @forelse($servicesSummary as $serviceName => $items)
                                <x-ui.badge :tone="$items->first()->enabled ? 'success' : 'neutral'">{{ $serviceName }}</x-ui.badge>
                            @empty
                                <x-ui.empty-state title="Sin servicios registrados" description="La instancia aún no publicó servicios." icon="◇" />
                            @endforelse
                        </div>
                    </x-ui.card>

                    <x-ui.card variant="metric" padding="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-[color:var(--color-text-secondary)]">Plugins</p>
                                <p class="mt-1 text-lg font-semibold">{{ $integration->plugins_count }}</p>
                            </div>
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-xs text-[color:var(--color-text-secondary)]">Registrados</span>
                        </div>
                        <div class="mt-3 space-y-2">
                            @forelse($integration->plugins as $plugin)
                                <div class="rounded-[var(--radius-control)] border border-white/5 bg-white/[0.03] px-3 py-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="truncate text-sm font-medium">{{ $plugin->name }}</p>
                                        <x-ui.badge tone="neutral">v{{ $plugin->version ?? 'n/a' }}</x-ui.badge>
                                    </div>
                                </div>
                            @empty
                                <x-ui.empty-state title="Sin plugins" description="La instancia todavía no registró plugins." icon="◇" />
                            @endforelse
                        </div>
                    </x-ui.card>

                    <x-ui.card variant="metric" padding="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-[color:var(--color-text-secondary)]">Capabilities disponibles</p>
                                <p class="mt-1 text-lg font-semibold">{{ $capabilitiesTotal }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-300">Disponibles</span>
                        </div>
                        <div class="mt-3 space-y-2">
                            @forelse($visibleCapabilities as $capability)
                                <div class="rounded-[var(--radius-control)] border border-white/5 bg-white/[0.03] px-3 py-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium font-mono">{{ $capability->service ?? $capability->capability }}</p>
                                            <p class="text-xs text-[color:var(--color-text-muted)]">Versión {{ $capability->version ?? '1.0' }}</p>
                                        </div>
                                        <x-ui.badge tone="success">Disponible</x-ui.badge>
                                    </div>
                                </div>
                            @empty
                                <x-ui.empty-state title="Sin capabilities" description="La instancia aún no publicó capacidades." icon="◇" />
                            @endforelse
                        </div>
                        @if($capabilitiesTotal > $visibleCapabilities->count())
                            <div class="mt-3">
                                <x-ui.button type="button" variant="ghost" size="sm">Ver todas</x-ui.button>
                            </div>
                        @endif
                    </x-ui.card>

                    <x-ui.card variant="metric" padding="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-[color:var(--color-text-secondary)]">Actividad reciente</p>
                                <p class="mt-1 text-lg font-semibold">{{ $integration->logs->count() }}</p>
                            </div>
                            <span class="rounded-full bg-sky-500/10 px-2.5 py-1 text-xs text-sky-200">Real</span>
                        </div>
                        <div class="mt-3 space-y-2">
                            @forelse($integration->logs as $log)
                                <article class="rounded-[var(--radius-control)] border border-white/5 bg-white/[0.03] p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium">{{ $log->event }}</p>
                                            <p class="mt-0.5 text-xs text-[color:var(--color-text-muted)]">{{ $log->message }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-white/5 px-2 py-1 text-[10px] text-[color:var(--color-text-muted)]">{{ $log->created_at?->diffForHumans() }}</span>
                                    </div>
                                </article>
                            @empty
                                <x-ui.empty-state title="Sin actividad reciente" description="No hay logs recientes para mostrar." icon="◇" />
                            @endforelse
                        </div>
                    </x-ui.card>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card>
                <x-ui.empty-state
                    title="No hay instancias n8n"
                    description="Genera un código de pairing y úsalo en el workflow CodeRED Pairing de n8n."
                    icon="◇"
                >
                    <x-ui.button wire:click="connect" variant="primary">Conectar con n8n</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @endforelse
    </div>
</div>
