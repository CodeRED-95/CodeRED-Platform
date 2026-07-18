<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold">{{ $mode === 'edit' ? 'Editar agencia' : 'Nueva agencia' }}</h2>
                <p class="text-sm text-slate-500">Agencias Shalom</p>
            </div>
            <a href="{{ route('admin.agencies.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm dark:border-slate-700">Volver</a>
        </div>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">
        @csrf
        @if ($errors->any())
            <x-ui.alert variant="danger" title="Revisa el formulario">
                Se encontraron errores de validación. Corrige los campos marcados antes de guardar.
            </x-ui.alert>
        @endif
        <div class="grid gap-6 xl:grid-cols-2">
            @php
                $sections = [
                    'Identificación' => ['code','name','short_name','slug','source','source_reference','source_text'],
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
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-lg font-semibold">{{ $title }}</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            @foreach ($fields as $field)
                                @if ($field === 'services')
                                    <label class="md:col-span-2">
                                        <span class="text-sm font-medium">Servicios</span>
                                        <textarea wire:model.blur="servicesInput" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700"></textarea>
                                        @error('servicesInput') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                    </label>
                                @elseif ($field === 'is_operations_center')
                                    <label class="flex items-center gap-3 md:col-span-2">
                                        <input type="checkbox" wire:model="is_operations_center" class="rounded border-slate-300">
                                        <span class="text-sm font-medium">Centro de Operaciones</span>
                                    </label>
                                @elseif ($field === 'has_moved')
                                    <label class="flex items-center gap-3 md:col-span-2">
                                        <input type="checkbox" wire:model.live="has_moved" class="rounded border-slate-300">
                                        <span class="text-sm font-medium">La agencia se trasladó</span>
                                    </label>
                                @elseif (in_array($field, ['status', 'size'], true))
                                    <label>
                                        <span class="text-sm font-medium">{{ $field === 'status' ? 'Estado' : 'Tamaño' }}</span>
                                        <select wire:model="{{ $field }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700">
                                            @foreach(($field === 'status' ? $statuses : $sizes) as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @elseif ($field === 'moved_to_agency_id')
                                    @if ($has_moved)
                                    <div class="md:col-span-2" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-sm font-medium">Agencia destino</span>
                                            @if ($moved_to_agency_id)
                                                <button type="button" wire:click="selectDestination(null)" class="text-xs font-medium text-[color:var(--color-brand-light)] hover:text-white">Limpiar</button>
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
                                                <input type="search" wire:model.live.debounce.350ms="destinationSearch" placeholder="Buscar por código, nombre o ubicación" class="w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-4 py-3 text-sm text-[color:var(--color-text-primary)] placeholder:text-[color:var(--color-text-muted)] focus-ring">
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
                                        @error('moved_to_agency_id') <span class="mt-1 block text-sm text-[color:var(--color-danger)]">{{ $message }}</span> @enderror
                                    </div>
                                    @endif
                                @elseif (in_array($field, ['moved_to_address','moved_at','move_notice'], true))
                                    @if ($has_moved)
                                        <label class="{{ $field === 'move_notice' ? 'md:col-span-2' : '' }}">
                                            <span class="text-sm font-medium">{{ $field === 'moved_to_address' ? 'Nueva dirección' : ($field === 'moved_at' ? 'Fecha de traslado' : 'Aviso público') }}</span>
                                            @if ($field === 'moved_at')
                                                <input type="date" wire:model.blur="moved_at" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700">
                                            @else
                                                <textarea wire:model.blur="{{ $field }}" rows="{{ $field === 'move_notice' ? 4 : 2 }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700"></textarea>
                                            @endif
                                        </label>
                                    @endif
                                @elseif ($field === 'observations')
                                    <label class="md:col-span-2">
                                        <span class="text-sm font-medium">Observaciones</span>
                                        <textarea wire:model.blur="observations" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700"></textarea>
                                    </label>
                                @else
                                    <label>
                                        <span class="text-sm font-medium">{{ str_replace('_', ' ', ucfirst($field)) }}</span>
                                        <input type="{{ in_array($field, ['latitude','longitude'], true) ? 'number' : ($field === 'email' ? 'email' : ($field === 'moved_at' ? 'date' : 'text')) }}" step="any" wire:model.blur="{{ $field }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700">
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white dark:bg-white dark:text-slate-900">
                <span wire:loading.remove wire:target="save">Guardar</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </button>
        </div>
    </form>
</div>
