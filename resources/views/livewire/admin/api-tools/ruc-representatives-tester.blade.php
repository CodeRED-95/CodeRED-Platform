<div class="space-y-6">
    <x-ui.page-header title="Buscar Representantes" subtitle="Consulta exclusivamente los representantes legales declarados en SUNAT." />

    <x-ui.alert tone="info">
        Esta consulta solo obtiene representantes legales desde SUNAT. La razón social, el estado,
        domicilio y demás datos del contribuyente continúan siendo atendidos por la base local de RUC.
    </x-ui.alert>

    <x-ui.card title="Consulta por RUC" description="Ingresa un RUC válido de 11 dígitos.">
        <form wire:submit="consult" class="grid gap-5 lg:grid-cols-3">
            <x-ui.input id="representatives-ruc" wire:model="ruc" label="RUC" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" required :error="$errors->first('ruc')" />
            <div class="flex items-end gap-3 lg:col-span-2">
                <x-ui.button type="submit" loading-target="consult">Buscar</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="clear">Limpiar</x-ui.button>
                <span wire:loading wire:target="consult" role="status">Consultando SUNAT…</span>
            </div>
        </form>
    </x-ui.card>

    @if($errorMessage)
        <x-ui.alert tone="danger">{{ $errorMessage }}</x-ui.alert>
    @endif

    @if($representantes !== null)
        <x-ui.card title="Representantes legales" description="Datos normalizados desde la consulta pública de SUNAT.">
            <div class="mb-5 flex flex-wrap items-center gap-3">
                <x-ui.badge tone="success">{{ trans_choice(':count representante|:count representantes', count($representantes), ['count' => count($representantes)]) }}</x-ui.badge>
                <x-ui.badge>{{ $technical['cached'] ? 'Caché' : 'SUNAT' }}</x-ui.badge>
                <span class="text-sm">RUC {{ $ruc }} · {{ $technical['response_time_ms'] }} ms</span>
            </div>
            <div class="mb-5 flex flex-wrap gap-3">
                <x-ui.button type="button" variant="secondary" data-codered-copy="{{ $copyDataText }}" data-codered-copy-label="Datos">Copiar datos</x-ui.button>
                <x-ui.button type="button" variant="secondary" data-codered-copy="{{ $copyJson }}" data-codered-copy-label="JSON">Copiar JSON</x-ui.button>
            </div>

            @if($representantes === [])
                <x-ui.empty-state title="Sin representantes" description="SUNAT respondió correctamente pero no devolvió representantes legales." icon="♙" />
            @else
                <x-ui.table caption="Representantes legales consultados en SUNAT">
                    <thead>
                        <tr>
                            <th scope="col">Tipo documento</th>
                            <th scope="col">Número documento</th>
                            <th scope="col">Nombre</th>
                            <th scope="col">Cargo</th>
                            <th scope="col">Fecha desde</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($representantes as $representante)
                            <tr wire:key="representante-{{ $representante['numero_documento'] }}-{{ $loop->index }}">
                                <td>{{ $representante['tipo_documento'] ?: '—' }}</td>
                                <td class="font-mono">{{ $representante['numero_documento'] ?: '—' }}</td>
                                <td>{{ $representante['nombre'] ?: '—' }}</td>
                                <td>{{ $representante['cargo'] ?: '—' }}</td>
                                <td>{{ $representante['fecha_desde'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif

    @if($technical)
        <x-ui.card title="Detalles técnicos">
            <dl class="grid gap-3 md:grid-cols-2">
                <div>Código HTTP: {{ $technical['http_status'] }}</div>
                <div>Tiempo total: {{ $technical['response_time_ms'] }} ms</div>
                <div>Origen: {{ $technical['source'] }}</div>
                <div>Caché utilizada: {{ $technical['cached'] ? 'Sí' : 'No' }}</div>
                <div>Representantes: {{ $technical['representative_count'] }}</div>
                <div>Consultado: {{ $technical['consulted_at'] }}</div>
            </dl>
        </x-ui.card>
    @endif
</div>
