<div class="space-y-8">
    <x-ui.page-header
        title="Agencias Shalom"
        subtitle="Administración y consulta del módulo Agencies."
    >
        <x-slot:actions>
            @if(auth()->user()->hasPermission('agencies.export'))
                <div class="relative" x-data="{ open: false }">
                    <x-ui.button type="button" variant="secondary" x-on:click="open = ! open" x-bind:aria-expanded="open">Exportar</x-ui.button>
                    <div x-cloak x-show="open" x-on:click.outside="open = false" class="layer-popover absolute right-0 mt-2 w-72 space-y-1 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] p-2 shadow-2xl">
                        <a href="{{ $filteredExportUrl }}" class="focus-ring block rounded-xl px-3 py-2 text-sm hover:bg-white/5">Exportar resultados filtrados (.json)</a>
                        <a href="{{ $allExportUrl }}" class="focus-ring block rounded-xl px-3 py-2 text-sm hover:bg-white/5">Exportar todas las agencias (.json)</a>
                        @if(auth()->user()->hasPermission('agencies.backup.create'))
                            <button type="button" wire:click="createBackup" class="focus-ring block w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-white/5">Crear copia de seguridad</button>
                        @endif
                        @if(auth()->user()->hasPermission('agencies.backup.view'))
                            <a href="{{ route('admin.agencies.backups.index') }}" class="focus-ring block rounded-xl px-3 py-2 text-sm hover:bg-white/5">Ver copias de seguridad</a>
                        @endif
                    </div>
                </div>
            @endif
            @can('create', \App\Modules\Agencies\Models\Agency::class)
                <x-ui.button href="{{ route('admin.agencies.create') }}" variant="primary">Nueva agencia</x-ui.button>
            @endcan
            @can('import', \App\Modules\Agencies\Models\Agency::class)
                <x-ui.button href="{{ route('admin.agencies.import') }}" variant="secondary">Importar</x-ui.button>
            @endcan
            <x-ui.button href="{{ route('admin.agencies.map') }}" variant="secondary">Ver mapa</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        @foreach ($stats as $label => $value)
            <x-ui.stat-card :label="str_replace('_', ' ', ucfirst($label))" :value="$value" tone="{{ $label === 'active' ? 'success' : ($label === 'under_review' ? 'info' : ($label === 'moved' ? 'warning' : ($label === 'operations_centers' ? 'brand' : 'neutral'))) }}" />
        @endforeach
    </div>

    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="ID, Code, agencia o identificador chosen..." />
            <x-ui.status-select id="agencies-status-filter" wire:model.live="status" label="Estado" :value="$status" :options="$statuses" />
            <x-ui.dropdown-select id="agencies-department-filter" wire:model.live="department" label="Departamento" :value="$department" :options="['' => 'Todos'] + $departments->mapWithKeys(fn ($item) => [$item => $item])->all()" />
            <x-ui.dropdown-select id="agencies-province-filter" wire:model.live="province" label="Provincia" :value="$province" :options="['' => 'Todas'] + $provinces->mapWithKeys(fn ($item) => [$item => $item])->all()" />
            <x-ui.dropdown-select id="agencies-district-filter" wire:model.live="district" label="Distrito" :value="$district" :options="['' => 'Todos'] + $districts->mapWithKeys(fn ($item) => [$item => $item])->all()" />
            <x-ui.dropdown-select id="agencies-size-filter" wire:model.live="size" label="Tamaño" :value="$size" :options="$sizes" />
            <x-ui.dropdown-select id="agencies-operations-filter" wire:model.live="operationsCenter" label="Centro de Operaciones" :value="$operationsCenter" :options="['' => 'Todos', '1' => 'Sí', '0' => 'No']" />
            <x-ui.dropdown-select id="agencies-moved-filter" wire:model.live="moved" label="Trasladadas" :value="$moved" :options="['' => 'Todas', '1' => 'Sí', '0' => 'No']" />
            <x-ui.dropdown-select id="agencies-source-filter" wire:model.live="source" label="Fuente" :value="$source" :options="['' => 'Todas', 'github_gist' => 'GitHub Gist', 'manual' => 'Manual', 'seed' => 'Seeder']" />
            <x-ui.dropdown-select id="agencies-coordinates-filter" wire:model.live="withoutCoordinates" label="Coordenadas" :value="$withoutCoordinates" :options="['' => 'Todas', '1' => 'Sin coordenadas']" />
            <x-ui.dropdown-select id="agencies-phone-filter" wire:model.live="withoutPhone" label="Teléfono" :value="$withoutPhone" :options="['' => 'Todos', '1' => 'Sin teléfono']" />
            <x-ui.dropdown-select id="agencies-deleted-filter" wire:model.live="withTrashed" label="Eliminadas" :value="$withTrashed" :options="['' => 'Activas', 'only' => 'Papelera', 'with' => 'Todas']" />
            <x-ui.dropdown-select id="agencies-review-filter" wire:model.live="underReview" label="Revisión" :value="$underReview" :options="['' => 'Todas', '1' => 'Solo en revisión']" />
            <x-ui.dropdown-select id="agencies-per-page" wire:model.live="perPage" label="Por página" :value="$perPage" :options="[15 => '15', 30 => '30', 50 => '50', 100 => '100']" />
        </div>
    </x-ui.card>

    @if (($bulkSummary['selected'] ?? 0) > 0)
        <x-ui.card class="sticky bottom-4 z-30 border-[color:var(--color-brand)]/40" padding="p-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold">{{ $bulkSummary['selected'] }} {{ $bulkSummary['selected'] === 1 ? 'agencia seleccionada' : 'agencias seleccionadas' }}</p>
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Selección limitada a las agencias visibles de esta página y a 100 registros por operación.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($trashView)
                        @if ($canBulkRestore)
                            <x-ui.confirm-dialog
                                id="bulk-restore-agencies"
                                title="Restaurar agencias seleccionadas"
                                :message="'Se restaurarán '.$bulkSummary['selected'].' agencias y volverán al listado principal. Los conflictos de Code, ID externo o slug se omitirán de forma segura.'"
                                confirm-label="Restaurar agencias"
                                confirm-action="restoreSelected"
                                tone="primary"
                            >
                                <x-slot:trigger><x-ui.button type="button" wire:click="prepareBulkAction('restore')" variant="primary" loading-target="restoreSelected">Restaurar seleccionadas</x-ui.button></x-slot:trigger>
                            </x-ui.confirm-dialog>
                        @endif
                        @if ($canBulkForceDelete)
                            <x-ui.confirm-dialog
                                id="bulk-force-delete-agencies"
                                title="Eliminar agencias definitivamente"
                                :message="'Esta acción no se puede deshacer. Se eliminarán permanentemente '.$bulkSummary['selected'].' agencias y sus historiales dependientes. Vista previa: '.implode(', ', $bulkSummary['preview_names']).($bulkSummary['selected'] > count($bulkSummary['preview_names']) ? ' y otras.' : '.')"
                                confirm-label="Eliminar definitivamente"
                                confirm-action="forceDeleteSelected"
                                confirmation-text="ELIMINAR"
                            >
                                <x-slot:trigger><x-ui.button type="button" wire:click="prepareBulkAction('force-delete')" variant="danger" loading-target="forceDeleteSelected">Eliminar definitivamente</x-ui.button></x-slot:trigger>
                            </x-ui.confirm-dialog>
                        @endif
                    @else
                        @if ($canBulkActivate)
                            <x-ui.confirm-dialog
                                id="bulk-activate-agencies"
                                title="Activar agencias seleccionadas"
                                :message="'Se seleccionaron '.$bulkSummary['selected'].' agencias. '.$bulkSummary['reviewable'].' están En revisión y se activarán; '.($bulkSummary['selected'] - $bulkSummary['reviewable']).' serán ignoradas.'"
                                confirm-label="Activar agencias"
                                confirm-action="activateSelected"
                                tone="primary"
                            >
                                <x-slot:trigger><x-ui.button type="button" wire:click="prepareBulkAction('activate')" variant="primary" loading-target="activateSelected">Activar seleccionadas</x-ui.button></x-slot:trigger>
                            </x-ui.confirm-dialog>
                        @endif
                        @if ($canBulkDelete)
                            <x-ui.confirm-dialog
                                id="bulk-delete-agencies"
                                title="Enviar agencias a la papelera"
                                :message="'Se enviarán '.$bulkSummary['selected'].' agencias a la papelera y podrán restaurarse. Vista previa: '.implode(', ', $bulkSummary['preview_names']).($bulkSummary['selected'] > count($bulkSummary['preview_names']) ? ' y otras.' : '.')"
                                confirm-label="Eliminar agencias"
                                confirm-action="deleteSelected"
                            >
                                <x-slot:trigger><x-ui.button type="button" wire:click="prepareBulkAction('delete')" variant="danger" loading-target="deleteSelected">Eliminar seleccionadas</x-ui.button></x-slot:trigger>
                            </x-ui.confirm-dialog>
                        @endif
                    @endif
                    <x-ui.button type="button" wire:click="clearSelection" variant="secondary">Limpiar selección</x-ui.button>
                </div>
            </div>
            <x-ui.form-error :message="$errors->first('selectedAgencyIds')" />
        </x-ui.card>
    @endif

    <div wire:loading.delay wire:target="search,status,department,province,district,size,operationsCenter,moved,source,withoutCoordinates,withoutPhone,withTrashed,underReview,perPage">
        <x-ui.skeleton variant="table" :rows="5" />
    </div>

    <x-ui.table id="agencies-list" wire:loading.class="opacity-50" wire:target="search,status,department,province,district,size,operationsCenter,moved,source,withoutCoordinates,withoutPhone,withTrashed,underReview,perPage">
        <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-[color:var(--color-text-secondary)]">
            <tr>
                <th class="w-12 px-5 py-4">
                    <label class="inline-flex items-center">
                        <span class="sr-only">Seleccionar todas las agencias de esta página</span>
                        <input
                            type="checkbox"
                            wire:click="togglePageSelection"
                            @checked($allPageSelected)
                            x-data
                            x-effect="$el.indeterminate = @js(($bulkSummary['selected'] ?? 0) > 0 && ! $allPageSelected)"
                            aria-label="Seleccionar todas las agencias de esta página"
                            class="size-4 rounded border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-brand)] focus-ring"
                        >
                    </label>
                </th>
                <th class="px-5 py-4">ID</th>
                <th class="cursor-pointer px-5 py-4" wire:click="sortBy('code')">Code</th>
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
                <tr @class(['transition hover:bg-white/5', 'opacity-70' => $agency->trashed()])>
                    <td class="px-5 py-4">
                        <label class="inline-flex items-center">
                            <span class="sr-only">Seleccionar agencia {{ $agency->name }}</span>
                            <input type="checkbox" wire:model.live="selectedAgencyIds" value="{{ $agency->id }}" aria-label="Seleccionar agencia {{ $agency->name }}" class="size-4 rounded border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-brand)] focus-ring">
                        </label>
                    </td>
                    <td class="px-5 py-4 font-mono text-sm">{{ $agency->external_id ?? '—' }}</td>
                    <td class="px-5 py-4 font-mono text-sm text-[color:var(--color-accent-ivory)]">{{ $agency->code }}</td>
                    <td class="px-5 py-4">
                        <div class="font-medium">{{ $agency->name }}</div>
                        <div class="text-xs text-[color:var(--color-text-secondary)]">{{ $agency->short_name ?? $agency->source_reference ?? '—' }}</div>
                    </td>
                    <td class="px-5 py-4">
                        <div>{{ $agency->department }}</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @if ($agency->texto_chosen_terrestre)<x-ui.badge tone="success">Terrestre</x-ui.badge>@endif
                            @if ($agency->texto_chosen_aereo)<x-ui.badge tone="info">Aéreo</x-ui.badge>@endif
                            @if (! $agency->texto_chosen_terrestre && ! $agency->texto_chosen_aereo)<x-ui.badge tone="neutral">Sin canal</x-ui.badge>@endif
                        </div>
                    </td>
                    <td class="px-5 py-4">{{ $agency->province }}</td>
                    <td class="px-5 py-4">{{ $agency->district }}</td>
                    <td class="px-5 py-4">
                        <x-ui.badge :tone="$agency->is_operations_center ? 'brand' : 'neutral'">
                            {{ $agency->is_operations_center ? 'Centro de Operaciones' : 'No' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-2">
                            <x-ui.badge :tone="$agency->status?->value === 'active' ? 'success' : ($agency->status?->value === 'under_review' ? 'info' : ($agency->status?->value === 'moved' ? 'warning' : 'neutral'))">
                                {{ $agency->statusLabel() }}
                            </x-ui.badge>
                            @if ($agency->trashed())
                                <x-ui.badge tone="danger">En papelera</x-ui.badge>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-4 text-sm text-[color:var(--color-text-secondary)]">{{ optional($agency->updated_at)->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-2">
                            @if ($agency->trashed())
                                @can('restore', $agency)
                                    <x-ui.confirm-dialog id="restore-agency-{{ $agency->id }}" title="Restaurar agencia" message="La agencia volverá a los listados activos." confirm-label="Restaurar" confirm-action="restoreAgency({{ $agency->id }})" tone="primary">
                                        <x-slot:trigger><x-ui.button size="sm" variant="primary">Restaurar</x-ui.button></x-slot:trigger>
                                    </x-ui.confirm-dialog>
                                @endcan
                                @can('forceDelete', $agency)
                                    <x-ui.confirm-dialog id="force-delete-agency-{{ $agency->id }}" title="Eliminar agencia definitivamente" message="Esta acción es irreversible y eliminará permanentemente la agencia." confirm-label="Eliminar definitivamente" confirm-action="forceDeleteAgency({{ $agency->id }})">
                                        <x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar definitivamente</x-ui.button></x-slot:trigger>
                                    </x-ui.confirm-dialog>
                                @endcan
                            @else
                                <x-ui.button href="{{ route('admin.agencies.show', $agency) }}" size="sm" variant="outline">Ver</x-ui.button>
                                <x-ui.button href="{{ route('admin.agencies.edit', $agency) }}" size="sm" variant="secondary">Editar</x-ui.button>
                                @can('delete', $agency)
                                    <x-ui.confirm-dialog id="delete-agency-{{ $agency->id }}" title="Mover agencia a la papelera" message="Podrás restaurarla más adelante desde el filtro Papelera." confirm-label="Mover a papelera" confirm-action="deleteAgency({{ $agency->id }})">
                                        <x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar</x-ui.button></x-slot:trigger>
                                    </x-ui.confirm-dialog>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-5 py-12">
                        <x-ui.empty-state
                            title="No hay agencias registradas"
                            description="Crea una agencia nueva o importa el JSON del Gist para empezar."
                            icon="⌁"
                        >
                            <div class="flex justify-center gap-3">
                                @can('create', \App\Modules\Agencies\Models\Agency::class)<x-ui.button href="{{ route('admin.agencies.create') }}" variant="primary">Crear agencia</x-ui.button>@endcan
                                @can('import', \App\Modules\Agencies\Models\Agency::class)<x-ui.button href="{{ route('admin.agencies.import') }}" variant="secondary">Importar</x-ui.button>@endcan
                            </div>
                        </x-ui.empty-state>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <x-ui.pagination :paginator="$agencies" scroll-to="#agencies-list" />
</div>
