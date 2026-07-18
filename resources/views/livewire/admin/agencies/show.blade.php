<div class="space-y-8">
    <x-ui.page-header
        :title="$agency->name"
        :subtitle="$agency->code.' · '.$agency->department.' / '.$agency->province.' / '.$agency->district"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.agencies.edit', $agency) }}" variant="secondary">Editar</x-ui.button>
            <x-ui.button href="{{ route('admin.agencies.index') }}" variant="outline">Volver</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($agency->has_moved)
        <x-ui.alert tone="warning">
            <div class="font-semibold">Esta agencia se trasladó.</div>
            @if ($agency->movedToAgency)
                <p class="mt-2">Ahora atiende en <a class="underline" href="{{ route('admin.agencies.show', $agency->movedToAgency) }}">{{ $agency->movedToAgency->name }}</a>.</p>
                <p class="mt-1 text-sm">{{ $agency->movedToAgency->address }}</p>
            @else
                <p class="mt-2">Nueva dirección: {{ $agency->moved_to_address }}</p>
            @endif
            @if ($agency->move_notice)
                <p class="mt-2 text-sm">{{ $agency->move_notice }}</p>
            @endif
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
        <x-ui.card>
            <x-ui.section-header title="Información general" />
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Dirección</p>
                    <p class="mt-1">{{ $agency->address }}</p>
                </div>
                <div>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Referencia</p>
                    <p class="mt-1">{{ $agency->reference ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Teléfono</p>
                    <p class="mt-1">{{ $agency->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Correo</p>
                    <p class="mt-1">{{ $agency->email ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Horario</p>
                    <p class="mt-1">{{ $agency->schedule ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Servicios</p>
                    <p class="mt-1">{{ is_array($agency->services) ? implode(', ', $agency->services) : '—' }}</p>
                </div>
            </div>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card>
                <x-ui.section-header title="Estado" />
                <div class="mt-4 space-y-3">
                    <x-ui.badge :tone="$agency->status?->value === 'active' ? 'success' : ($agency->status?->value === 'under_review' ? 'info' : ($agency->status?->value === 'moved' ? 'warning' : 'neutral'))">
                        {{ $agency->statusLabel() }}
                    </x-ui.badge>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Versión {{ $agency->data_version }}</p>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Actualizado {{ optional($agency->updated_at)->format('d/m/Y H:i') }}</p>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header title="Clasificación" />
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.badge :tone="$agency->is_operations_center ? 'brand' : 'neutral'">
                        {{ $agency->is_operations_center ? 'Centro de Operaciones' : 'No es CO' }}
                    </x-ui.badge>
                    @if ($agency->size)
                        <x-ui.badge tone="ivory">{{ $agency->size->label() }}</x-ui.badge>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Ubicación" />
            <div class="mt-4 space-y-3 text-sm">
                <p>{{ $agency->department }} / {{ $agency->province }} / {{ $agency->district }}</p>
                <p class="text-[color:var(--color-text-secondary)]">{{ $agency->latitude && $agency->longitude ? $agency->latitude.', '.$agency->longitude : 'Sin coordenadas' }}</p>
                @if ($agency->map_url)
                    <x-ui.button href="{{ $agency->map_url }}" target="_blank" variant="outline">Abrir en Google Maps</x-ui.button>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="Historial reciente" />
            <div class="mt-4 space-y-3">
                @forelse ($history as $item)
                    <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-medium">{{ $item->action }}</p>
                            <span class="text-xs text-[color:var(--color-text-secondary)]">{{ $item->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">{{ $item->user_agent }}</p>
                    </div>
                @empty
                    <x-ui.empty-state title="Sin historial" description="Todavía no hay cambios registrados para esta agencia." icon="⌁" />
                @endforelse
            </div>
        </x-ui.card>
    </div>
</div>
