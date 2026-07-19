<div class="space-y-8">
    <x-ui.page-header title="Mapa de agencias" subtitle="Explora las agencias con coordenadas, filtra su ubicación y abre rutas en Google Maps.">
        <x-slot:actions><x-ui.button href="{{ route('admin.agencies.index') }}" variant="secondary">Ver listado</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Agencias visibles" :value="$mappedCount" tone="info" />
        <x-ui.stat-card label="Coincidencias" :value="$totalMatching" tone="success" />
        <x-ui.stat-card label="Sin coordenadas" :value="$withoutCoordinates" tone="warning" />
    </div>

    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-3">
            <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="Código, nombre o ubicación…" />
            <x-ui.status-select id="map-status-filter" wire:model.live="status" label="Estado" :value="$status" :options="$statuses" />
            <x-ui.dropdown-select id="map-department-filter" wire:model.live="department" label="Departamento" :value="$department" :options="['' => 'Todos'] + $departments->mapWithKeys(fn ($item) => [$item => $item])->all()" />
        </div>
    </x-ui.card>

    <div wire:loading.delay wire:target="search,status,department"><x-ui.skeleton variant="card" :rows="2" /></div>

    <x-ui.card padding="p-0" class="overflow-hidden" wire:loading.class="opacity-50" wire:target="search,status,department">
        @if ($totalMatching > $markerLimit)
            <div class="border-b border-[color:var(--color-border-subtle)] p-4"><x-ui.alert tone="warning">Se muestran las primeras {{ number_format($markerLimit) }} agencias. Usa los filtros para acotar el mapa.</x-ui.alert></div>
        @endif

        @if ($clusters !== [])
            <div class="grid min-h-[38rem] lg:grid-cols-[minmax(0,1fr)_22rem]" x-data="{ selected: @js($clusters[0]['id']) }">
                <section class="relative min-h-[32rem] overflow-hidden border-b border-[color:var(--color-border-subtle)] bg-[color:var(--color-background)] lg:border-b-0 lg:border-r" aria-label="Mapa geográfico de agencias">
                    <div class="absolute inset-0 opacity-40" aria-hidden="true" style="background-image: linear-gradient(var(--color-border-subtle) 1px, transparent 1px), linear-gradient(90deg, var(--color-border-subtle) 1px, transparent 1px); background-size: 3rem 3rem;"></div>
                    <div class="absolute inset-x-[24%] inset-y-[5%] rotate-[-8deg] rounded-[48%_52%_44%_56%] border border-[color:var(--color-border)] bg-[color:var(--color-surface)]/50 shadow-2xl" aria-hidden="true"></div>
                    <div class="absolute left-5 top-5 z-10 rounded-xl border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)]/90 px-3 py-2 text-xs text-[color:var(--color-text-secondary)] backdrop-blur">Perú · ubicación aproximada</div>
                    @foreach ($clusters as $cluster)
                        <button type="button" class="focus-ring absolute z-20 inline-flex h-9 min-w-9 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-[color:var(--color-info)] px-2 text-xs font-bold text-white shadow-xl transition hover:scale-110" style="left: {{ $cluster['left'] }}%; top: {{ $cluster['top'] }}%;" x-on:click="selected = '{{ $cluster['id'] }}'" x-bind:aria-pressed="selected === '{{ $cluster['id'] }}'" aria-label="{{ $cluster['count'] === 1 ? 'Ver '.$cluster['agencies'][0]['name'] : 'Ver grupo de '.$cluster['count'].' agencias' }}">{{ $cluster['count'] }}</button>
                    @endforeach
                </section>

                <aside class="max-h-[38rem] overflow-y-auto bg-[color:var(--color-background-elevated)] p-5" aria-live="polite">
                    @foreach ($clusters as $cluster)
                        <div x-cloak x-show="selected === '{{ $cluster['id'] }}'" class="space-y-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-[color:var(--color-text-muted)]">{{ $cluster['count'] === 1 ? 'Agencia seleccionada' : $cluster['count'].' agencias agrupadas' }}</p>
                                <p class="mt-1 font-mono text-xs text-[color:var(--color-accent-ivory)]">{{ number_format($cluster['latitude'], 6) }}, {{ number_format($cluster['longitude'], 6) }}</p>
                            </div>
                            @foreach ($cluster['agencies'] as $agency)
                                <article class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div><p class="font-mono text-xs text-[color:var(--color-accent-ivory)]">{{ $agency['code'] }}</p><h2 class="mt-1 font-semibold text-white">{{ $agency['name'] }}</h2></div>
                                        <x-ui.badge :tone="$agency['status'] === 'active' ? 'success' : ($agency['status'] === 'under_review' ? 'info' : ($agency['status'] === 'moved' ? 'warning' : 'neutral'))">{{ $agency['status_label'] }}</x-ui.badge>
                                    </div>
                                    <p class="mt-3 text-sm text-[color:var(--color-text-secondary)]">{{ $agency['location'] }}</p>
                                    <p class="mt-1 text-sm text-[color:var(--color-text-muted)]">{{ $agency['address'] ?: 'Dirección no registrada' }}</p>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <x-ui.button href="{{ $agency['detail_url'] }}" size="sm" variant="outline">Ver detalle</x-ui.button>
                                        <x-ui.button href="{{ $agency['maps_url'] }}" size="sm" variant="secondary" target="_blank" rel="noopener noreferrer" aria-label="Abrir {{ $agency['name'] }} en Google Maps">Abrir Google Maps</x-ui.button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endforeach
                </aside>
            </div>
        @else
            <div class="p-8"><x-ui.empty-state title="No hay agencias para mostrar" description="Ajusta los filtros o registra coordenadas válidas en las agencias." icon="◎"><x-ui.button href="{{ route('admin.agencies.index') }}" variant="secondary">Volver al listado</x-ui.button></x-ui.empty-state></div>
        @endif
    </x-ui.card>

    <p class="text-sm text-[color:var(--color-text-muted)]">Los puntos se proyectan desde las coordenadas registradas y los cercanos se agrupan para facilitar la lectura. La cartografía es orientativa; Google Maps se abre únicamente cuando lo solicitas.</p>
</div>
