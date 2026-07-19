<div class="space-y-8">
    <x-ui.page-header title="Mapa de agencias" subtitle="Explora agencias sobre cartografía real, filtra resultados y abre rutas externas.">
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

        @if ($markers !== [])
            <div
                wire:key="agency-map-{{ $mapKey }}"
                x-data="codeRedAgencyMap(@js(['markers' => $markers, 'markerUrl' => asset('images/branding/codered-symbol.png')]))"
                x-on:codered-agency-map:destroy.window="destroy()"
                class="grid lg:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <section class="relative border-b border-[color:var(--color-border-subtle)] lg:border-b-0 lg:border-r" aria-label="Mapa cartográfico de agencias">
                    <div x-ref="map" wire:ignore data-codered-agency-map class="h-[28rem] w-full bg-[color:var(--color-surface)] md:h-[34rem] lg:h-[42rem]" role="region" aria-label="Mapa interactivo con {{ $mappedCount }} agencias"></div>
                </section>

                <aside class="max-h-[42rem] overflow-y-auto bg-[color:var(--color-background-elevated)] p-4" aria-label="Resultados del mapa">
                    <div class="space-y-3">
                        @foreach ($markers as $agency)
                            <article class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] p-4">
                                <button type="button" class="focus-ring w-full rounded-lg text-left" x-on:click="focusAgency({{ $agency['id'] }})" aria-label="Centrar mapa en {{ $agency['name'] }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-mono text-xs text-[color:var(--color-accent-ivory)]">{{ $agency['code'] }}</p>
                                            <h2 class="mt-1 truncate font-semibold text-white">{{ $agency['name'] }}</h2>
                                        </div>
                                        <x-ui.badge :tone="$agency['status'] === 'active' ? 'success' : ($agency['status'] === 'under_review' ? 'info' : ($agency['status'] === 'moved' ? 'warning' : 'neutral'))">{{ $agency['status_label'] }}</x-ui.badge>
                                    </div>
                                    <p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">{{ $agency['location'] }}</p>
                                </button>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-ui.button href="{{ $agency['detail_url'] }}" size="sm" variant="outline">Ver detalle</x-ui.button>
                                    <x-ui.button href="{{ $agency['maps_url'] }}" size="sm" variant="secondary" target="_blank" rel="noopener noreferrer" aria-label="Abrir {{ $agency['name'] }} en Google Maps">Abrir Google Maps</x-ui.button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </aside>
            </div>
        @else
            <div class="p-8"><x-ui.empty-state title="No hay agencias para mostrar" description="Ajusta los filtros o registra coordenadas válidas en las agencias." icon="◎"><x-ui.button href="{{ route('admin.agencies.index') }}" variant="secondary">Volver al listado</x-ui.button></x-ui.empty-state></div>
        @endif
    </x-ui.card>

    @if ($withoutCoordinates > 0)
        <x-ui.card title="Agencias sin ubicación registrada" description="Estos registros no generan marcadores porque sus coordenadas faltan o están fuera del rango válido.">
            <div class="flex flex-wrap gap-2">
                @foreach ($unmappedAgencies as $agency)
                    <x-ui.button href="{{ route('admin.agencies.show', $agency) }}" size="sm" variant="outline">{{ $agency->code }} · {{ $agency->name }}</x-ui.button>
                @endforeach
                @if ($withoutCoordinates > $unmappedAgencies->count())
                    <x-ui.badge tone="neutral">y {{ $withoutCoordinates - $unmappedAgencies->count() }} más</x-ui.badge>
                @endif
            </div>
        </x-ui.card>
    @endif
</div>
