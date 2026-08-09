<div class="space-y-6">
    <x-ui.page-header title="Detalle RUC" subtitle="Información interna del padrón reducido SUNAT.">
        <x-slot:actions><x-ui.button href="{{ route('admin.ruc.records') }}" variant="secondary">Volver al padrón</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-ui.card><dl class="grid gap-4 md:grid-cols-2">
        @foreach(['ruc' => 'RUC', 'razon_social' => 'Nombre o razón social', 'estado' => 'Estado del contribuyente', 'condicion' => 'Condición de domicilio', 'ubigeo' => 'Ubigeo', 'departamento' => 'Departamento', 'provincia' => 'Provincia', 'distrito' => 'Distrito', 'direccion' => 'Dirección'] as $field => $label)
            <div class="rounded-[var(--radius-control)] bg-white/5 p-4"><dt class="text-xs text-[color:var(--color-text-muted)]">{{ $label }}</dt><dd class="mt-1">{{ $record->{$field} ?? '—' }}</dd>
                @if(in_array($field, ['ruc', 'razon_social', 'direccion', 'ubigeo'], true) && $record->{$field})<x-ui.button type="button" variant="ghost" class="mt-2" x-on:click="$dispatch('codered-copy', { value: @js($record->{$field}) })">Copiar {{ strtolower($label) }}</x-ui.button>@endif
            </div>
        @endforeach
    </dl></x-ui.card>

    {{-- Columnas que el LISTADO no trae a propósito: solo se cargan aquí.
         El listado selecciona 10 columnas de las 22 para no arrastrar el
         desglose de dirección en cada una de las 50 filas de cada página. --}}
    <x-ui.card>
        <h3 class="text-base font-semibold mb-1">Desglose de la dirección</h3>
        <p class="text-sm text-[color:var(--color-text-secondary)] mb-4">
            Campos del padrón que no aparecen en el listado; se consultan solo al abrir el detalle.
        </p>
        <dl class="grid gap-4 md:grid-cols-3">
            @foreach([
                'tipo_via' => 'Tipo de vía',
                'nombre_via' => 'Nombre de vía',
                'numero' => 'Número',
                'interior' => 'Interior',
                'lote' => 'Lote',
                'manzana' => 'Manzana',
                'kilometro' => 'Kilómetro',
                'departamento_direccion' => 'Departamento (dirección)',
                'tipo_zona' => 'Tipo de zona',
                'codigo_zona' => 'Código de zona',
            ] as $field => $label)
                <div class="rounded-[var(--radius-control)] bg-white/5 p-4">
                    <dt class="text-xs text-[color:var(--color-text-muted)]">{{ $label }}</dt>
                    <dd class="mt-1">{{ $record->{$field} ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.card>
</div>
