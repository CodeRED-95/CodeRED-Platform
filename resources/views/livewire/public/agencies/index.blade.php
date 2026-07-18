<div class="space-y-8">
    <x-ui.page-header
        title="Agencias Shalom"
        subtitle="Consulta pública de agencias activas con búsqueda, filtros y acceso rápido a contacto."
    />

    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.input wire:model.live.debounce.400ms="search" type="search" label="Buscar" placeholder="Código, nombre o ubicación..." />
            <x-ui.dropdown-select id="public-department" wire:model.live="department" label="Departamento" :value="$department" :options="['' => 'Todos'] + $departments->mapWithKeys(fn ($item) => [$item => $item])->all()" />
            <x-ui.dropdown-select id="public-province" wire:model.live="province" label="Provincia" :value="$province" :options="['' => 'Todas'] + $provinces->mapWithKeys(fn ($item) => [$item => $item])->all()" />
            <x-ui.dropdown-select id="public-district" wire:model.live="district" label="Distrito" :value="$district" :options="['' => 'Todos'] + $districts->mapWithKeys(fn ($item) => [$item => $item])->all()" />
        </div>
    </x-ui.card>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($agencies as $agency)
            <article class="rounded-[var(--radius-card)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] p-5 transition hover:bg-[color:var(--color-surface-hover)]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.24em] text-[color:var(--color-text-muted)]">{{ $agency->code }}</p>
                        <h2 class="mt-1 text-xl font-semibold">{{ $agency->name }}</h2>
                        <p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">{{ $agency->department }} / {{ $agency->province }} / {{ $agency->district }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <x-ui.badge tone="success">Activa</x-ui.badge>
                        @if($agency->is_operations_center)
                            <x-ui.badge tone="brand">Centro de Operaciones</x-ui.badge>
                        @endif
                    </div>
                </div>

                <p class="mt-4 text-sm text-[color:var(--color-text-secondary)]">{{ $agency->address }}</p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <x-ui.button href="{{ route('public.agencies.show', $agency->code) }}" variant="secondary" size="sm">Ver detalle</x-ui.button>
                    @if($agency->map_url)
                        <x-ui.button href="{{ $agency->map_url }}" target="_blank" variant="outline" size="sm">Google Maps</x-ui.button>
                    @endif
                </div>
            </article>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-ui.empty-state title="No se encontraron agencias" description="Prueba con otro filtro o una búsqueda más amplia." icon="⌁" />
            </div>
        @endforelse
    </div>

    <x-ui.pagination :paginator="$agencies" />
</div>
