<div class="space-y-6">
    <x-ui.page-header
        title="CodeRED Anime"
        subtitle="Busca anime, revisa episodios y resuelve una fuente compatible sin acoplar la UI a JkAnime."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('api.docs') }}" variant="secondary">Documentacion API</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 lg:grid-cols-4">
        @foreach([
            ['label' => 'Buscar', 'description' => 'Catalogo unificado', 'active' => $searched],
            ['label' => 'Anime', 'description' => 'Metadata AniList + provider', 'active' => $anime !== null],
            ['label' => 'Episodios', 'description' => 'Lista normalizada', 'active' => $episode !== null],
            ['label' => 'Stream', 'description' => 'Fuente para cliente compatible', 'active' => $stream !== null],
        ] as $step)
            <x-ui.card>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--color-text-muted)]">{{ $step['description'] }}</p>
                        <h2 class="mt-2 text-lg font-semibold">{{ $step['label'] }}</h2>
                    </div>
                    <x-ui.badge :tone="$step['active'] ? 'success' : 'neutral'">{{ $step['active'] ? 'Listo' : 'Pendiente' }}</x-ui.badge>
                </div>
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card title="Buscar anime" description="La consulta pasa por CodeRED Anime API Services; la vista no llama proveedores externos directamente.">
        <form wire:submit="search" class="grid gap-4 lg:grid-cols-[1fr_auto]">
            <x-ui.input
                id="anime-query"
                wire:model="query"
                label="Titulo"
                placeholder="one piece, naruto, sousou no frieren..."
                minlength="2"
                maxlength="120"
                required
                :error="$errors->first('query')"
            />
            <div class="flex items-end gap-3">
                <x-ui.button type="submit" loading-target="search">Buscar</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="clear">Limpiar</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if($errorMessage)
        <x-ui.alert tone="danger">{{ $errorMessage }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <x-ui.card title="Resultados" description="Selecciona un titulo para cargar metadata, episodios y relaciones conocidas.">
                @if($searched && $results === [])
                    <div class="rounded-[var(--radius-panel)] border border-dashed border-white/15 p-8 text-center text-[color:var(--color-text-muted)]">
                        No encontramos resultados para esta busqueda. Prueba con un titulo alternativo o romaji.
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($results as $item)
                            <button
                                type="button"
                                wire:key="anime-result-{{ $item['id'] }}"
                                wire:click="selectAnime('{{ $item['id'] }}')"
                                class="group overflow-hidden rounded-[var(--radius-panel)] border border-white/10 bg-white/[0.03] text-left transition hover:-translate-y-0.5 hover:border-[color:var(--color-primary)]/60 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-[color:var(--color-primary)]"
                            >
                                <div class="flex gap-4 p-4">
                                    @if($item['poster'] ?? null)
                                        <img src="{{ $item['poster'] }}" alt="" class="h-28 w-20 rounded-[var(--radius-control)] object-cover shadow-lg shadow-black/30">
                                    @else
                                        <div class="flex h-28 w-20 items-center justify-center rounded-[var(--radius-control)] bg-white/10 text-xs text-[color:var(--color-text-muted)]">Sin poster</div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="truncate text-base font-semibold">{{ $item['title'] }}</h3>
                                            @if($item['year'] ?? null)
                                                <x-ui.badge>{{ $item['year'] }}</x-ui.badge>
                                            @endif
                                        </div>
                                        <p class="mt-2 line-clamp-3 text-sm text-[color:var(--color-text-muted)]">{{ $item['description'] ?? 'Metadata pendiente de sincronizacion.' }}</p>
                                        @if(($item['genres'] ?? []) !== [])
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach(array_slice($item['genres'], 0, 3) as $genre)
                                                    <x-ui.badge tone="neutral">{{ $genre }}</x-ui.badge>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            @if($anime)
                <x-ui.card title="{{ $anime['title'] }}" description="Detalle unificado de CodeRED Anime.">
                    <div class="grid gap-5 lg:grid-cols-[14rem_1fr]">
                        @if($anime['poster'] ?? null)
                            <img src="{{ $anime['poster'] }}" alt="" class="h-80 w-full rounded-[var(--radius-panel)] object-cover shadow-xl shadow-black/30">
                        @else
                            <div class="flex h-80 items-center justify-center rounded-[var(--radius-panel)] bg-white/10 text-[color:var(--color-text-muted)]">Sin poster</div>
                        @endif
                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-2">
                                <x-ui.badge>{{ $anime['id'] }}</x-ui.badge>
                                @if($anime['episodes'] ?? null)
                                    <x-ui.badge tone="success">{{ $anime['episodes'] }} episodios</x-ui.badge>
                                @endif
                                @if($anime['status'] ?? null)
                                    <x-ui.badge tone="neutral">{{ $anime['status'] }}</x-ui.badge>
                                @endif
                            </div>
                            <p class="text-sm leading-6 text-[color:var(--color-text-muted)]">{{ $anime['description'] ?? 'Sin descripcion disponible.' }}</p>
                            @if(($anime['genres'] ?? []) !== [])
                                <div class="flex flex-wrap gap-2">
                                    @foreach($anime['genres'] as $genre)
                                        <x-ui.badge tone="neutral">{{ $genre }}</x-ui.badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-6">
            <x-ui.card title="Episodios" description="Carga el episodio para consultar servidores disponibles.">
                @if($episodes === [])
                    <p class="text-sm text-[color:var(--color-text-muted)]">Selecciona un anime para ver episodios.</p>
                @else
                    <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                        @foreach($episodes as $item)
                            <button
                                type="button"
                                wire:key="anime-episode-{{ $item['number'] }}"
                                wire:click="selectEpisode({{ $item['number'] }})"
                                class="w-full rounded-[var(--radius-control)] border px-4 py-3 text-left transition {{ ($episode['number'] ?? null) === $item['number'] ? 'border-[color:var(--color-primary)] bg-[color:var(--color-primary)]/10' : 'border-white/10 bg-white/[0.03] hover:border-white/25 hover:bg-white/[0.06]' }}"
                            >
                                <span class="block font-semibold">Episodio {{ $item['number'] }}</span>
                                <span class="mt-1 block truncate text-sm text-[color:var(--color-text-muted)]">{{ $item['title'] ?? 'Sin titulo' }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Servidores" description="El fallback real se resuelve en el servicio, con prioridad configurable.">
                @if($servers === [])
                    <p class="text-sm text-[color:var(--color-text-muted)]">Selecciona un episodio para listar servidores.</p>
                @else
                    <div class="space-y-2">
                        @foreach($servers as $server)
                            <button
                                type="button"
                                wire:key="anime-server-{{ $server['id'] }}"
                                wire:click="selectServer('{{ $server['id'] }}')"
                                aria-pressed="{{ $selectedServer === $server['id'] ? 'true' : 'false' }}"
                                class="w-full rounded-[var(--radius-control)] border px-4 py-3 text-left transition {{ $selectedServer === $server['id'] ? 'border-[color:var(--color-primary)] bg-[color:var(--color-primary)]/10' : 'border-white/10 bg-white/[0.03] hover:border-white/25 hover:bg-white/[0.06]' }}"
                            >
                                <span class="flex items-center justify-between gap-3">
                                    <span class="font-semibold">{{ $server['name'] }}</span>
                                    <x-ui.badge tone="neutral">{{ $server['type'] }}</x-ui.badge>
                                </span>
                                <span class="mt-1 block text-sm text-[color:var(--color-text-muted)]">Idioma: {{ $server['language'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <x-ui.button type="button" class="mt-4 w-full" wire:click="resolveStream" loading-target="resolveStream">Resolver stream</x-ui.button>
                @endif
            </x-ui.card>

            @if($stream)
                <x-ui.card title="Fuente resuelta" description="Informacion minima para Jellyfin o cliente compatible.">
                    <div class="mb-4 flex flex-wrap gap-2">
                        <x-ui.badge tone="success">{{ strtoupper((string) $stream['format']) }}</x-ui.badge>
                        <x-ui.badge>{{ $stream['type'] }}</x-ui.badge>
                    </div>
                    <div class="rounded-[var(--radius-control)] bg-black/30 p-3 text-xs text-[color:var(--color-text-muted)]">
                        <code class="break-all">{{ $stream['url'] }}</code>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <x-ui.button type="button" variant="secondary" data-codered-copy="{{ $stream['url'] }}" data-codered-copy-label="URL del stream">Copiar URL</x-ui.button>
                        @if(($stream['format'] ?? null) === 'mp4')
                            <x-ui.button href="{{ $stream['url'] }}" variant="secondary" target="_blank" rel="noopener noreferrer">Abrir MP4</x-ui.button>
                        @endif
                    </div>
                    @if(($stream['format'] ?? null) === 'm3u8')
                        <x-ui.alert class="mt-4" tone="info">HLS queda disponible para Jellyfin u otro cliente compatible. No se carga hls.js aqui para evitar un player duplicado.</x-ui.alert>
                    @endif
                    @if(($stream['headers'] ?? []) !== [])
                        <p class="mt-4 text-sm text-[color:var(--color-text-muted)]">Esta fuente requiere headers tecnicos controlados por la API; no se muestran secretos en el panel.</p>
                    @endif
                </x-ui.card>
            @endif
        </aside>
    </div>
</div>
