<div class="space-y-6">
    <x-ui.page-header title="Padrón RUC" subtitle="Consulta paginada del padrón reducido SUNAT almacenado en PostgreSQL.">
        <x-slot:actions>@if(auth()->user()->hasPermission('ruc.import-history'))<x-ui.button href="{{ route('admin.ruc.imports') }}" variant="secondary">Importaciones</x-ui.button>@endif</x-slot:actions>
    </x-ui.page-header>
    <x-ui.card><div class="grid gap-3 md:grid-cols-3">
        <x-ui.search-box wire:model.live.debounce.500ms="search" label="RUC o razón social" placeholder="20123456789 o empresa" />
        <x-ui.dropdown-select id="ruc-estado" wire:model.live="estado" label="Estado" :value="$estado" :options="$estados" />
        <x-ui.dropdown-select id="ruc-condicion" wire:model.live="condicion" label="Condición" :value="$condicion" :options="$condiciones" />
        <x-ui.input wire:model.live.debounce.500ms="departamento" label="Departamento" />
        <x-ui.input wire:model.live.debounce.500ms="provincia" label="Provincia" />
        <x-ui.input wire:model.live.debounce.500ms="distrito" label="Distrito" />
    </div></x-ui.card>
    <x-ui.table><thead><tr><th class="px-5 py-4">RUC</th><th class="px-5 py-4">Razón social</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Condición</th><th class="px-5 py-4">Ubigeo</th><th class="px-5 py-4">Ubicación</th><th class="px-5 py-4">Dirección</th></tr></thead><tbody class="divide-y divide-white/5">
        @forelse($records as $record)<tr><td class="px-5 py-4 font-mono">{{ $record->ruc }}</td><td class="px-5 py-4 font-medium">{{ $record->razon_social }}</td><td class="px-5 py-4"><x-ui.badge tone="info">{{ $record->estado ?? '—' }}</x-ui.badge></td><td class="px-5 py-4">{{ $record->condicion ?? '—' }}</td><td class="px-5 py-4 font-mono">{{ $record->ubigeo ?? '—' }}</td><td class="px-5 py-4">{{ implode(' · ', array_filter([$record->departamento, $record->provincia, $record->distrito])) ?: '—' }}</td><td class="max-w-md px-5 py-4">{{ $record->direccion ?? '—' }}</td></tr>
        @empty<tr><td colspan="7" class="px-5 py-12"><x-ui.empty-state title="No hay registros RUC" description="Importa un padrón reducido SUNAT o ajusta los filtros." /></td></tr>@endforelse
    </tbody></x-ui.table>
    <x-ui.pagination :paginator="$records" />
</div>
