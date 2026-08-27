<div class="space-y-6">
    <x-ui.page-header title="Buscar DNI por nombres" subtitle="Consulta referencial por nombres y apellidos a través del proveedor DNIPERU." />

    @unless($featureEnabled && $providerEnabled)
        <x-ui.alert tone="warning">
            La búsqueda por nombres está desactivada.
            @unless($featureEnabled) Falta <code>DNI_NAME_SEARCH_ENABLED=true</code>. @endunless
            @unless($providerEnabled) Falta <code>DNI_NAME_SEARCH_DNIPERU_ENABLED=true</code>. @endunless
            Actívala en el <code>.env</code> del servidor y vuelve a cachear la configuración.
        </x-ui.alert>
    @endunless

    <x-ui.alert tone="info">
        Los resultados son <strong>referenciales</strong>: proceden de un formulario público de terceros y
        no constituyen una validación oficial de RENIEC. Para datos oficiales usa la consulta por número de DNI.
    </x-ui.alert>

    <x-ui.card title="Búsqueda" description="Los tres campos son obligatorios; el proveedor no admite búsquedas parciales.">
        <form wire:submit="search" class="grid gap-5 lg:grid-cols-3">
            <x-ui.input id="name-search-nombres" wire:model="nombres" label="Nombres" maxlength="120" required :error="$errors->first('nombres')" />
            <x-ui.input id="name-search-paterno" wire:model="apellidoPaterno" label="Apellido paterno" maxlength="120" required :error="$errors->first('apellidoPaterno')" />
            <x-ui.input id="name-search-materno" wire:model="apellidoMaterno" label="Apellido materno" maxlength="120" required :error="$errors->first('apellidoMaterno')" />
            <div class="flex items-end gap-3 lg:col-span-3">
                <x-ui.button type="submit" loading-target="search">Buscar</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="clear">Limpiar</x-ui.button>
                <span wire:loading wire:target="search" role="status">Consultando al proveedor…</span>
            </div>
        </form>
    </x-ui.card>

    @if($errorMessage)
        <x-ui.alert tone="danger">{{ $errorMessage }}</x-ui.alert>
    @endif

    @if($matches !== null)
        <x-ui.card title="Coincidencias" description="Formato público de CodeRED Platform">
            <div class="mb-5 flex flex-wrap items-center gap-3">
                <x-ui.badge tone="success">{{ trans_choice(':count coincidencia|:count coincidencias', count($matches), ['count' => count($matches)]) }}</x-ui.badge>
                <x-ui.badge>{{ $technical['cache_hit'] ? 'Caché' : 'DNIPERU' }}</x-ui.badge>
                <x-ui.badge tone="warning">Referencial</x-ui.badge>
                <span class="text-sm">{{ $technical['response_time_ms'] }} ms · HTTP {{ $technical['http_status'] }}</span>
            </div>
            <div class="mb-5 flex flex-wrap gap-3">
                <x-ui.button type="button" variant="secondary" data-codered-copy="{{ $copyDataText }}" data-codered-copy-label="Datos">Copiar datos</x-ui.button>
                <x-ui.button type="button" variant="secondary" data-codered-copy="{{ $copyJson }}" data-codered-copy-label="JSON">Copiar JSON</x-ui.button>
            </div>

            @if($matches === [])
                <x-ui.empty-state title="Sin coincidencias" description="El proveedor respondió correctamente pero no devolvió resultados para esa combinación." icon="⌕" />
            @else
                <x-ui.table caption="Coincidencias de la búsqueda por nombres">
                    <thead>
                        <tr>
                            <th scope="col">DNI</th>
                            <th scope="col">Nombres</th>
                            <th scope="col">Apellido paterno</th>
                            <th scope="col">Apellido materno</th>
                            <th scope="col"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matches as $match)
                            <tr wire:key="match-{{ $match['dni'] }}-{{ $loop->index }}">
                                <td class="font-mono">{{ $match['dni'] }}</td>
                                <td>{{ $match['nombres'] }}</td>
                                <td>{{ $match['apellido_paterno'] }}</td>
                                <td>{{ $match['apellido_materno'] }}</td>
                                <td class="whitespace-nowrap">
                                    <x-ui.button type="button" variant="secondary" data-codered-copy="{{ $match['dni'] }}" data-codered-copy-label="DNI">Copiar DNI</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif

    @if($technical)
        <x-ui.card title="Detalles técnicos">
            <details>
                <summary class="cursor-pointer font-medium">Mostrar detalles seguros</summary>
                <dl class="mt-4 grid gap-3 md:grid-cols-2">
                    <div>Código HTTP: {{ $technical['http_status'] }}</div>
                    <div>Tiempo total: {{ $technical['response_time_ms'] ?? 'N/D' }} ms</div>
                    <div>Estado del proveedor: {{ $technical['result_status'] }}</div>
                    <div>Código del proveedor: {{ $technical['provider_status_code'] ?? 'N/D' }}</div>
                    <div>Caché utilizada: {{ $technical['cache_hit'] ? 'Sí' : 'No' }}</div>
                    <div>Proveedor consultado: {{ $technical['provider_called'] ? 'Sí' : 'No' }}</div>
                    <div>Coincidencias: {{ $technical['match_count'] }}</div>
                    <div>Caché de resultados: {{ $cacheEnabled ? 'Activada' : 'Desactivada' }}</div>
                    <div>Ability equivalente: dni:nombre</div>
                    <div>Fecha y hora: {{ $technical['searched_at'] }}</div>
                </dl>
            </details>
        </x-ui.card>
    @endif
</div>
