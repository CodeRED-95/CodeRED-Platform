<div class="space-y-8">
    <x-ui.page-header
        :title="$agency->name"
        :subtitle="$agency->code.' · '.$agency->place"
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
            <x-ui.section-header title="Chosen" />
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
        <div class="space-y-6">
            <x-ui.card>
                <x-ui.section-header title="Ubicación" />
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">Place</p>
                        <p class="mt-1">{{ $agency->place }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">Zone</p>
                        <p class="mt-1">{{ $agency->zone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">Dirección</p>
                        <p class="mt-1">{{ $agency->address }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">Departamento</p>
                        <p class="mt-1">{{ $agency->department }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">Provincia</p>
                        <p class="mt-1">{{ $agency->province }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">Distrito</p>
                        <p class="mt-1">{{ $agency->district }}</p>
                    </div>
                </div>
            </x-ui.card>
            <x-ui.card>
                <x-ui.section-header title="Horarios" />
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-sm text-[color:var(--color-text-secondary)]">General</dt><dd class="mt-1">{{ $agency->schedule_general ?? 'No registrado' }}</dd></div>
                    <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Domingos</dt><dd class="mt-1">{{ $agency->schedule_sunday ?? 'No registrado' }}</dd></div>
                </dl>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card>
                <x-ui.section-header title="Estado" />
                <div class="mt-4 space-y-3">
                    <x-ui.badge :tone="$agency->status?->value === 'active' ? 'success' : ($agency->status?->value === 'under_review' ? 'info' : ($agency->status?->value === 'moved' ? 'warning' : 'neutral'))">
                        {{ $agency->statusLabel() }}
                    </x-ui.badge>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Actualizado {{ optional($agency->updated_at)->format('d/m/Y H:i') }}</p>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header title="Clasificación" />
                <div class="mt-4 space-y-3">
                    <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Categoría</dt><dd class="mt-1">{{ $agency->classification_category ?? '—' }}</dd></div>
                    <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Envíos</dt><dd class="mt-1">{{ $agency->classification_sends_category ?? '—' }}</dd></div>
                    <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Recibos</dt><dd class="mt-1">{{ $agency->classification_receives_category ?? '—' }}</dd></div>
                </div>
            </x-ui.card>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Coordenadas y Mapa" />
            <div class="mt-4 space-y-3 text-sm">
                <p class="text-[color:var(--color-text-secondary)]">{{ $agency->latitude && $agency->longitude ? $agency->latitude.', '.$agency->longitude : 'Sin coordenadas' }}</p>
                @if ($agency->map_url)
                    <p><a href="{{ $agency->map_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-500 underline">{{ $agency->map_url }}</a></p>
                @endif
                <x-ui.map-preview class="mt-4" :latitude="$agency->latitude" :longitude="$agency->longitude" :name="$agency->name" :location="$agency->place" :label="'Ubicación de '.$agency->name" />
                @if ($agency->latitude && $agency->longitude)
                    <x-ui.button href="{{ $agency->map_url }}" target="_blank" rel="noopener noreferrer" variant="outline">Abrir en Google Maps</x-ui.button>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="Historial de nombres" />
            <div class="mt-4 space-y-3">
                @forelse ($nameHistories as $item)
                    <div class="text-sm">
                        <p><span class="font-bold">{{ $item->old_name }}</span> -> <span class="font-bold">{{ $item->new_name }}</span></p>
                        <p class="text-[color:var(--color-text-secondary)]">
                            {{ $item->changed_at->format('d/m/Y H:i') }}
                            @if($item->changedBy)
                                por {{ $item->changedBy->name }}
                            @endif
                            ({{ $item->source }})
                        </p>
                    </div>
                @empty
                    <x-ui.empty-state title="Sin historial" description="No hay cambios de nombre registrados para esta agencia." icon="⌁" />
                @endforelse
            </div>
        </x-ui.card>
    </div>
    
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
