<div class="space-y-8">
    <x-ui.page-header
        title="Agencias Shalom"
        subtitle="Administración y consulta del módulo Agencies."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.agencies.create') }}" variant="primary">Nueva agencia</x-ui.button>
            <x-ui.button href="{{ route('admin.agencies.import') }}" variant="secondary">Importar</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        @foreach ($stats as $label => $value)
            <x-ui.stat-card :label="str_replace('_', ' ', ucfirst($label))" :value="$value" tone="{{ $label === 'active' ? 'success' : ($label === 'under_review' ? 'info' : ($label === 'moved' ? 'warning' : ($label === 'operations_centers' ? 'brand' : 'neutral'))) }}" />
        @endforeach
    </div>

    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.input wire:model.live.debounce.400ms="search" type="search" label="Buscar" placeholder="Código, nombre o ubicación..." />
            <x-ui.select wire:model.live="status" label="Estado">
                <option value="">Todos</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="department" label="Departamento">
                <option value="">Todos</option>
                @foreach($departments as $item)
                    <option value="{{ $item }}">{{ $item }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="province" label="Provincia">
                <option value="">Todas</option>
                @foreach($provinces as $item)
                    <option value="{{ $item }}">{{ $item }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="district" label="Distrito">
                <option value="">Todos</option>
                @foreach($districts as $item)
                    <option value="{{ $item }}">{{ $item }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="size" label="Tamaño">
                @foreach($sizes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="operationsCenter" label="Centro de Operaciones">
                <option value="">Todos</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
            </x-ui.select>
            <x-ui.select wire:model.live="moved" label="Trasladadas">
                <option value="">Todas</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
            </x-ui.select>
            <x-ui.select wire:model.live="source" label="Fuente">
                <option value="">Todas</option>
                <option value="github_gist">GitHub Gist</option>
                <option value="manual">Manual</option>
                <option value="seed">Seeder</option>
            </x-ui.select>
            <x-ui.select wire:model.live="withoutCoordinates" label="Coordenadas">
                <option value="">Todas</option>
                <option value="1">Sin coordenadas</option>
            </x-ui.select>
            <x-ui.select wire:model.live="withoutPhone" label="Teléfono">
                <option value="">Todos</option>
                <option value="1">Sin teléfono</option>
            </x-ui.select>
            <x-ui.select wire:model.live="withTrashed" label="Eliminadas">
                <option value="">No incluir</option>
                <option value="1">Incluir</option>
            </x-ui.select>
            <x-ui.select wire:model.live="underReview" label="Revisión">
                <option value="">Todas</option>
                <option value="1">Solo en revisión</option>
            </x-ui.select>
            <x-ui.select wire:model.live="perPage" label="Por página">
                <option value="15">15</option>
                <option value="30">30</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </x-ui.select>
        </div>
    </x-ui.card>

    <x-ui.table>
        <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-[color:var(--color-text-secondary)]">
            <tr>
                <th class="cursor-pointer px-5 py-4" wire:click="sortBy('code')">Código</th>
                <th class="cursor-pointer px-5 py-4" wire:click="sortBy('name')">Nombre</th>
                <th class="px-5 py-4">Departamento</th>
                <th class="px-5 py-4">Provincia</th>
                <th class="px-5 py-4">Distrito</th>
                <th class="px-5 py-4">Centro de Operaciones</th>
                <th class="px-5 py-4">Estado</th>
                <th class="px-5 py-4">Actualización</th>
                <th class="px-5 py-4">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse ($agencies as $agency)
                <tr class="transition hover:bg-white/5">
                    <td class="px-5 py-4 font-mono text-sm text-[color:var(--color-accent-ivory)]">{{ $agency->code }}</td>
                    <td class="px-5 py-4">
                        <div class="font-medium">{{ $agency->name }}</div>
                        <div class="text-xs text-[color:var(--color-text-secondary)]">{{ $agency->short_name ?? $agency->source_reference ?? '—' }}</div>
                    </td>
                    <td class="px-5 py-4">{{ $agency->department }}</td>
                    <td class="px-5 py-4">{{ $agency->province }}</td>
                    <td class="px-5 py-4">{{ $agency->district }}</td>
                    <td class="px-5 py-4">
                        <x-ui.badge :tone="$agency->is_operations_center ? 'brand' : 'neutral'">
                            {{ $agency->is_operations_center ? 'Centro de Operaciones' : 'No' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-5 py-4">
                        <x-ui.badge :tone="$agency->status?->value === 'active' ? 'success' : ($agency->status?->value === 'under_review' ? 'info' : ($agency->status?->value === 'moved' ? 'warning' : 'neutral'))">
                            {{ $agency->statusLabel() }}
                        </x-ui.badge>
                    </td>
                    <td class="px-5 py-4 text-sm text-[color:var(--color-text-secondary)]">{{ optional($agency->updated_at)->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button href="{{ route('admin.agencies.show', $agency) }}" size="sm" variant="outline">Ver</x-ui.button>
                            <x-ui.button href="{{ route('admin.agencies.edit', $agency) }}" size="sm" variant="secondary">Editar</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-5 py-12">
                        <x-ui.empty-state
                            title="No hay agencias registradas"
                            description="Crea una agencia nueva o importa el JSON del Gist para empezar."
                            icon="⌁"
                        >
                            <div class="flex justify-center gap-3">
                                <x-ui.button href="{{ route('admin.agencies.create') }}" variant="primary">Crear agencia</x-ui.button>
                                <x-ui.button href="{{ route('admin.agencies.import') }}" variant="secondary">Importar</x-ui.button>
                            </div>
                        </x-ui.empty-state>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <x-ui.pagination :paginator="$agencies" />
</div>
