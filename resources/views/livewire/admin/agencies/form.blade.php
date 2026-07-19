<div class="space-y-6">
    <x-ui.page-header :title="$mode === 'edit' ? 'Editar agencia' : 'Nueva agencia'" subtitle="Agencias Shalom">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.agencies.index') }}" variant="secondary">Volver</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit.prevent="save" class="space-y-6">
        @csrf
        @if ($errors->any())
            <x-ui.alert tone="danger">
                <p class="font-medium">Revisa el formulario</p>
                Se encontraron errores de validación. Corrige los campos marcados antes de guardar.
            </x-ui.alert>
        @endif
        <div class="grid gap-6 xl:grid-cols-2">
            @php
                $sections = [
                    'Identificación' => ['code','name','short_name','slug'],
                    'Ubicación' => ['department','province','district','address','reference'],
                    'Contacto' => ['phone','secondary_phone','email'],
                    'Horario' => ['schedule'],
                    'Servicios' => ['services'],
                    'Mapa y coordenadas' => ['latitude','longitude','map_url'],
                    'Clasificación' => ['size','is_operations_center'],
                    'Estado' => ['status'],
                    'Traslado' => ['has_moved','moved_to_agency_id','moved_to_address','moved_at','move_notice'],
                    'Fuente y observaciones' => ['observations'],
                ];
            @endphp
            <div class="space-y-6 xl:col-span-2">
                @foreach($sections as $title => $fields)
                    <x-ui.card padding="p-5">
                        <x-ui.section-header :title="$title" />
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            @foreach ($fields as $field)
                                @if ($field === 'services')
                                    <x-ui.textarea wrapper-class="md:col-span-2" wire:model.blur="servicesInput" rows="3" label="Servicios" :error="$errors->first('servicesInput')" />
                                @elseif ($field === 'is_operations_center')
                                    <div class="md:col-span-2"><x-ui.toggle wire:model="is_operations_center">Centro de Operaciones</x-ui.toggle></div>
                                @elseif ($field === 'has_moved')
                                    <div class="md:col-span-2"><x-ui.toggle wire:model.live="has_moved">La agencia se trasladó</x-ui.toggle></div>
                                @elseif ($field === 'status')
                                    <x-ui.status-select
                                        id="agency-status"
                                        name="status"
                                        wire:model="status"
                                        label="Estado"
                                        :value="$status"
                                        :options="$statuses"
                                        :error="$errors->first('status')"
                                        required
                                    />
                                @elseif ($field === 'size')
                                    <x-ui.dropdown-select
                                        id="agency-size"
                                        name="size"
                                        wire:model="size"
                                        label="Tamaño"
                                        :value="$size"
                                        :options="['' => 'Sin especificar'] + $sizes"
                                        :error="$errors->first('size')"
                                    />
                                @elseif ($field === 'moved_to_agency_id')
                                    @if ($has_moved)
                                    <div class="md:col-span-2" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-sm font-medium">Agencia destino</span>
                                            @if ($moved_to_agency_id)
                                                <x-ui.button type="button" wire:click="selectDestination(null)" variant="link" size="sm">Limpiar</x-ui.button>
                                            @endif
                                        </div>
                                        <div class="mt-1 rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)]">
                                            <button
                                                type="button"
                                                x-on:click="open = !open"
                                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm text-[color:var(--color-text-primary)]"
                                            >
                                                <span class="truncate">
                                                    @if ($this->selectedDestination)
                                                        {{ $this->selectedDestination->code }} — {{ $this->selectedDestination->name }} · {{ $this->selectedDestination->department }} / {{ $this->selectedDestination->province }} / {{ $this->selectedDestination->district }}
                                                    @else
                                                        Selecciona una agencia
                                                    @endif
                                                </span>
                                                <span class="text-[color:var(--color-text-secondary)]">⌄</span>
                                            </button>
                                            <div x-show="open" x-cloak x-on:click.outside="open = false" class="border-t border-[color:var(--color-border-subtle)] p-3">
                                                <x-ui.input type="search" wire:model.live.debounce.350ms="destinationSearch" placeholder="Buscar por código, nombre o ubicación" />
                                                <div class="mt-3 max-h-72 overflow-y-auto rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]">
                                                    @forelse ($destinations as $destination)
                                                        <button
                                                            type="button"
                                                            wire:click="selectDestination({{ $destination->id }})"
                                                            x-on:click="open = false"
                                                            class="flex w-full flex-col gap-1 border-b border-[color:var(--color-border-subtle)] px-4 py-3 text-left text-sm transition last:border-b-0 hover:bg-white/5 focus-ring"
                                                        >
                                                            <span class="font-medium text-[color:var(--color-text-primary)]">{{ $destination->code }} — {{ $destination->name }}</span>
                                                            <span class="text-xs text-[color:var(--color-text-secondary)]">{{ $destination->department }} / {{ $destination->province }} / {{ $destination->district }}</span>
                                                            <span class="truncate text-xs text-[color:var(--color-text-muted)]">{{ $destination->address }}</span>
                                                        </button>
                                                    @empty
                                                        <div class="px-4 py-8 text-center text-sm text-[color:var(--color-text-secondary)]">
                                                            No hay agencias disponibles con ese filtro.
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </div>
                                        <x-ui.form-error :message="$errors->first('moved_to_agency_id')" />
                                    </div>
                                    @endif
                                @elseif (in_array($field, ['moved_to_address','moved_at','move_notice'], true))
                                    @if ($has_moved)
                                        <div class="{{ $field === 'move_notice' ? 'md:col-span-2' : '' }}">
                                            @if ($field === 'moved_at')
                                                <x-ui.input type="date" wire:model.blur="moved_at" label="Fecha de traslado" :error="$errors->first('moved_at')" />
                                            @else
                                                <x-ui.textarea wire:model.blur="{{ $field }}" rows="{{ $field === 'move_notice' ? 4 : 2 }}" :label="$field === 'moved_to_address' ? 'Nueva dirección' : 'Aviso público'" :error="$errors->first($field)" />
                                            @endif
                                        </div>
                                    @endif
                                @elseif ($field === 'observations')
                                    <x-ui.textarea wrapper-class="md:col-span-2" wire:model.blur="observations" rows="4" label="Observaciones" :error="$errors->first('observations')" />
                                @else
                                    <x-ui.input
                                        type="{{ in_array($field, ['latitude','longitude'], true) ? 'number' : ($field === 'email' ? 'email' : 'text') }}"
                                        step="any"
                                        wire:model.blur="{{ $field }}"
                                        :label="str_replace('_', ' ', ucfirst($field))"
                                        :error="$errors->first($field)"
                                    />
                                @endif
                            @endforeach
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <x-ui.button type="submit" variant="primary" size="lg" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Guardar</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </x-ui.button>
        </div>
    </form>
</div>
