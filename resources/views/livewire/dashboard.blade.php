@php
    $total = \App\Modules\Agencies\Models\Agency::query()->count();
    $active = \App\Modules\Agencies\Models\Agency::query()->where('status', 'active')->count();
    $review = \App\Modules\Agencies\Models\Agency::query()->where('status', 'under_review')->count();
    $moved = \App\Modules\Agencies\Models\Agency::query()->where('has_moved', true)->count();
    $co = \App\Modules\Agencies\Models\Agency::query()->where('is_operations_center', true)->count();
    $withoutCoordinates = \App\Modules\Agencies\Models\Agency::query()->whereNull('latitude')->whereNull('longitude')->count();
    $lastImport = \App\Modules\Agencies\Models\AgencyImport::query()->latest()->first();
    $needsReview = \App\Modules\Agencies\Models\Agency::query()->where('status', 'under_review')->latest('updated_at')->limit(5)->get();
    $recent = \App\Modules\Agencies\Models\Agency::query()->latest('updated_at')->limit(6)->get();
@endphp

<div class="space-y-8">
    <x-ui.page-header
        title="Bienvenido a CodeRED Platform"
        subtitle="Administra agencias, importaciones y servicios desde un solo lugar."
    >
        <x-slot:actions>
            @can('viewAny', \App\Modules\Agencies\Models\Agency::class)
                <x-ui.button href="{{ route('admin.agencies.index') }}" variant="primary">Administrar agencias</x-ui.button>
            @endcan
            @can('import', \App\Modules\Agencies\Models\Agency::class)
                <x-ui.button href="{{ route('admin.agencies.import') }}" variant="secondary">Importar</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <x-ui.stat-card label="Total de agencias" :value="$total" tone="brand" href="{{ route('admin.agencies.index') }}" />
        <x-ui.stat-card label="Activas" :value="$active" tone="success" />
        <x-ui.stat-card label="Centros de Operaciones" :value="$co" tone="ivory" />
        <x-ui.stat-card label="En revisión" :value="$review" tone="info" />
        <x-ui.stat-card label="Trasladadas" :value="$moved" tone="warning" />
        <x-ui.stat-card label="Sin coordenadas" :value="$withoutCoordinates" tone="danger" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
        <x-ui.card>
            <x-ui.section-header title="Actividad reciente" description="Agencias actualizadas más recientemente." />
            <div class="mt-5 space-y-3">
                @forelse ($recent as $agency)
                    <div class="flex items-center justify-between rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/5 px-4 py-3">
                        <div>
                            <p class="font-medium">{{ $agency->name }}</p>
                            <p class="text-sm text-[color:var(--color-text-secondary)]">{{ $agency->code }} · {{ $agency->department }} / {{ $agency->province }}</p>
                        </div>
                        <x-ui.badge :tone="$agency->status?->value === 'active' ? 'success' : ($agency->status?->value === 'under_review' ? 'info' : ($agency->status?->value === 'moved' ? 'warning' : 'neutral'))">
                            {{ $agency->statusLabel() }}
                        </x-ui.badge>
                    </div>
                @empty
                    <x-ui.empty-state title="Aún no hay actividad" description="Cuando existan agencias o importaciones aparecerán aquí." icon="⌁" />
                @endforelse
            </div>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card>
                <x-ui.section-header title="Última importación" />
                @if ($lastImport)
                    <div class="mt-4 space-y-2 text-sm text-[color:var(--color-text-secondary)]">
                        <p>Archivo: {{ $lastImport->original_filename }}</p>
                        <p>Estado: {{ $lastImport->status?->value ?? $lastImport->status }}</p>
                        <p>Procesadas: {{ $lastImport->imported_rows + $lastImport->updated_rows + $lastImport->skipped_rows }}</p>
                    </div>
                @else
                    <x-ui.empty-state title="Sin importaciones" description="La primera importación aparecerá aquí." icon="⇪" />
                @endif
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header title="Agencias que requieren revisión" />
                <div class="mt-4 space-y-2">
                    @forelse ($needsReview as $agency)
                        <a href="{{ route('admin.agencies.show', $agency) }}" class="block rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] px-4 py-3 transition hover:bg-white/5">
                            <p class="font-medium">{{ $agency->name }}</p>
                            <p class="text-sm text-[color:var(--color-text-secondary)]">{{ $agency->code }} · {{ $agency->district }}</p>
                        </a>
                    @empty
                        <x-ui.empty-state title="Todo en orden" description="No hay agencias en revisión por ahora." icon="✓" />
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
