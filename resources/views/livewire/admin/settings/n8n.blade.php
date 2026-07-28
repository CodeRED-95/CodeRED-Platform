<div class="space-y-6">
    <x-ui.page-header title="Integraciones > n8n" subtitle="Pairing, Discovery, Heartbeat y Capability Registry para múltiples instancias.">
        <x-slot:actions><x-ui.button wire:click="connect" loading-target="connect">Conectar con n8n</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    @if($pairing)
        <x-ui.alert tone="warning" title="Código de pairing activo">
            <div class="mt-3 grid gap-3 md:grid-cols-3">
                <div><p class="text-xs text-[color:var(--color-text-muted)]">Código</p><p class="font-mono text-2xl font-semibold">{{ $pairing->pair_code }}</p></div>
                <div><p class="text-xs text-[color:var(--color-text-muted)]">Duración</p><p>{{ $pairing->expires_at?->diffForHumans() }}</p></div>
                <div><p class="text-xs text-[color:var(--color-text-muted)]">Estado</p><x-ui.badge tone="warning">Pendiente</x-ui.badge></div>
            </div>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        @forelse($integrations as $integration)
            @php($connectionStatus = $integration->connectionStatus())
            @php($statusTone = match ($connectionStatus) { 'connected' => 'success', 'degraded' => 'warning', 'connecting' => 'warning', 'waiting_heartbeat' => 'warning', 'unpaired' => 'neutral', 'revoked' => 'danger', default => 'danger' })
            <x-ui.card>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><h2 class="text-xl font-semibold">{{ $integration->instance_name }}</h2><x-ui.badge :tone="$statusTone">{{ $integration->connectionLabel() }}</x-ui.badge><x-ui.badge tone="info">{{ $integration->version ?? 'Sin versión' }}</x-ui.badge></div>
                        <p class="mt-1 text-sm text-[color:var(--color-text-muted)]">{{ $integration->environment ?? 'sin entorno' }} · {{ $integration->hostname ?? 'sin host' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2"><x-ui.button size="sm" variant="secondary" wire:click="testConnection({{ $integration->id }})">Probar conexión</x-ui.button><x-ui.button size="sm" variant="secondary" wire:click="reconnect({{ $integration->id }})">Reconectar</x-ui.button><x-ui.button size="sm" variant="danger" wire:click="rotateSecret({{ $integration->id }})">Regenerar secreto</x-ui.button></div>
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-5"><x-ui.stat-card label="Ultimo heartbeat" :value="$integration->last_seen_at ? max(0, $integration->last_seen_at->diffInSeconds()) : 'sin confirmar'" description="segundos" /><x-ui.stat-card label="Ultimo discovery" :value="$integration->capabilities_count > 0 ? 'publicado' : 'sin discovery'" /><x-ui.stat-card label="Plugins" :value="$integration->plugins_count" /><x-ui.stat-card label="Capabilities" :value="$integration->capabilities_count" /><x-ui.stat-card label="Latency" :value="$integration->latency_ms ?? 0" description="ms" /></div>
                @if($lastTestIntegrationId === $integration->id && $lastTestResult)<x-ui.alert class="mt-4" :tone="$lastTestResult['ok'] ? 'success' : 'warning'" title="Resultado de prueba"><p>Tiempo: {{ $lastTestResult['latency_ms'] ?? '—' }} ms · Resultado: {{ $lastTestResult['message'] }}</p></x-ui.alert>@endif
                <div class="mt-5 grid gap-4 lg:grid-cols-3"><div><h3 class="font-medium">Servicios</h3><div class="mt-2 flex flex-wrap gap-1">@foreach($integration->services as $service)<x-ui.badge :tone="$service->enabled ? 'success' : 'neutral'">{{ $service->service }}</x-ui.badge>@endforeach</div></div><div><h3 class="font-medium">Plugins</h3><div class="mt-2 space-y-1">@foreach($integration->plugins as $plugin)<p class="text-sm">{{ $plugin->name }} <span class="text-[color:var(--color-text-muted)]">v{{ $plugin->version ?? 'n/a' }}</span></p>@endforeach</div></div><div><h3 class="font-medium">Capabilities</h3><div class="mt-2 space-y-1">@foreach($integration->capabilities as $capability)<p class="text-sm"><span class="font-mono">{{ $capability->service ?? $capability->capability }}</span> <span class="text-[color:var(--color-text-muted)]">v{{ $capability->version ?? '1.0' }}</span></p>@endforeach</div></div></div>
                <div class="mt-5"><h3 class="font-medium">Logs</h3><div class="mt-2 space-y-2">@foreach($integration->logs as $log)<article class="rounded-[var(--radius-control)] border border-white/10 p-3"><p class="text-sm font-medium">{{ $log->event }}</p><p class="text-xs text-[color:var(--color-text-muted)]">{{ $log->created_at?->format('d/m/Y H:i:s') }} · {{ $log->message }}</p></article>@endforeach</div></div>
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty-state title="No hay instancias n8n" description="Genera un código de pairing y úsalo en el workflow CodeRED Pairing de n8n." icon="◇" /></x-ui.card>
        @endforelse
    </div>
</div>