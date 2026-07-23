<div class="space-y-8">
    <x-ui.page-header
        :title="$agency->name"
        :subtitle="$agency->code.' · '.$agency->department.' / '.$agency->province.' / '.$agency->district"
    >
        <x-slot:actions>
            @can('update', $agency)
                <x-ui.button href="{{ route('admin.agencies.edit', $agency) }}" variant="secondary">Editar</x-ui.button>
            @endcan
            <x-ui.button href="{{ route('admin.agencies.index') }}" variant="outline">Volver</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Identificación" />
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                <div><dt class="text-sm text-[color:var(--color-text-secondary)]">ID</dt><dd class="mt-1 font-mono">{{ $agency->external_id ?? 'No registrado' }}</dd></div>
                <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Code</dt><dd class="mt-1 font-mono">{{ $agency->code }}</dd></div>
                <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Agencia</dt><dd class="mt-1">{{ $agency->name }}</dd></div>
                @if ($agency->old_name)
                    <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Nombre anterior</dt><dd class="mt-1">{{ $agency->old_name }}</dd></div>
                @endif
            </dl>
        </x-ui.card>
        <x-ui.card>
            <x-ui.section-header title="Identificadores de extensión" />
            <dl class="mt-5 space-y-4">
                <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Texto Chosen Terrestre</dt><dd class="mt-1 break-words text-sm">{{ $agency->texto_chosen_terrestre ?? 'No registrado' }}</dd></div>
                <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Texto Chosen Aéreo</dt><dd class="mt-1 break-words text-sm">{{ $agency->texto_chosen_aereo ?? 'No registrado' }}</dd></div>
            </dl>
        </x-ui.card>
    </div>

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
                <div class="mt-4 space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <x-ui.badge :tone="$agency->is_operations_center ? 'brand' : 'neutral'">
                            {{ $agency->is_operations_center ? 'Centro de Operaciones' : 'No es CO' }}
                        </x-ui.badge>
                        @if ($agency->size)
                            <x-ui.badge tone="ivory">{{ $agency->size->label() }}</x-ui.badge>
                        @endif
                        <x-ui.badge tone="ivory">{{ $agency->category->value }}</x-ui.badge>
                    </div>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">{{ $agency->category->limitations() }}</p>
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
                <x-ui.map-preview class="mt-4" :latitude="$agency->latitude" :longitude="$agency->longitude" :name="$agency->name" :location="$agency->department.' / '.$agency->province.' / '.$agency->district" :label="'Ubicación de '.$agency->name" />
                @if ($agency->latitude && $agency->longitude)
                    <x-ui.button href="{{ 'https://www.google.com/maps/search/?api=1&query='.$agency->latitude.','.$agency->longitude }}" target="_blank" rel="noopener noreferrer" variant="outline">Abrir en Google Maps</x-ui.button>
                @endif
            </div>
        </x-ui.card>

        @if ($canViewHistory)
            <x-ui.card>
                <x-ui.section-header title="Historial de auditoría" description="Responsables, cambios y contexto de cada evento." />
                <div class="mt-4 space-y-3">
                    @forelse ($history as $item)
                        <x-ui.audit-entry :entry="$item" />
                    @empty
                        <x-ui.empty-state title="Sin historial" description="Todavía no hay cambios registrados para esta agencia." icon="⌁" />
                    @endforelse
                </div>
            </x-ui.card>
        @endif
    </div>
</div>
