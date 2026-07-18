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
                                    <label class="md:col-span-2">
                                        <span class="text-sm font-medium">Agencia destino</span>
                                        <input type="search" wire:model.live.debounce.400ms="destinationSearch" placeholder="Buscar por código, nombre o ubicación" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700">
                                        <select wire:model="moved_to_agency_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700">
                                            <option value="">Selecciona una agencia</option>
                                            @foreach($destinations as $destination)
                                                <option value="{{ $destination->id }}">{{ $destination->code }} · {{ $destination->name }} · {{ $destination->department }} / {{ $destination->province }} / {{ $destination->district }}</option>
                                            @endforeach
                                        </select>
                                    </label>
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
            <button type="submit" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white dark:bg-white dark:text-slate-900">Guardar</button>
        </div>
    </form>
</div>
