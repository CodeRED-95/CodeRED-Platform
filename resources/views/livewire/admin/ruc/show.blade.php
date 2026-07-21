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
</div>
