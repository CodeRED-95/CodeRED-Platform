<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach ($stats as $label => $value)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-sm text-slate-500">{{ str_replace('_', ' ', ucfirst($label)) }}</div>
                <div class="mt-2 text-3xl font-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-semibold">Agencias Shalom</h2>
                <p class="text-sm text-slate-500">Administración y consulta del módulo Agencies.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.agencies.create') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-slate-900">Nueva agencia</a>
                <a href="{{ route('admin.agencies.import') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm dark:border-slate-700">Importar</a>
            </div>
        </div>
        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar agencia..." class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
            <select wire:model.live="status" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Estado</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="department" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Departamento</option>
                @foreach($departments as $item)
                    <option value="{{ $item }}">{{ $item }}</option>
                @endforeach
            </select>
            <select wire:model.live="province" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Provincia</option>
                @foreach($provinces as $item)
                    <option value="{{ $item }}">{{ $item }}</option>
                @endforeach
            </select>
            <select wire:model.live="district" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Distrito</option>
                @foreach($districts as $item)
                    <option value="{{ $item }}">{{ $item }}</option>
                @endforeach
            </select>
            <select wire:model.live="size" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                @foreach($sizes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="operationsCenter" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Centro de Operaciones</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
            </select>
            <select wire:model.live="moved" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Trasladadas</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
            </select>
            <select wire:model.live="source" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Fuente</option>
                <option value="github_gist">GitHub Gist</option>
                <option value="manual">Manual</option>
                <option value="seed">Seeder</option>
            </select>
            <select wire:model.live="withoutCoordinates" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Sin coordenadas</option>
                <option value="1">Sí</option>
            </select>
            <select wire:model.live="withoutPhone" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Sin teléfono</option>
                <option value="1">Sí</option>
            </select>
            <select wire:model.live="withTrashed" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Sin eliminadas</option>
                <option value="1">Incluir eliminadas</option>
            </select>
            <select wire:model.live="underReview" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Todas</option>
                <option value="1">Solo en revisión</option>
            </select>
            <select wire:model.live="perPage" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="15">15 por página</option>
                <option value="30">30 por página</option>
                <option value="50">50 por página</option>
                <option value="100">100 por página</option>
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-950">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="cursor-pointer px-4 py-3" wire:click="sortBy('code')">Código</th>
                    <th class="cursor-pointer px-4 py-3" wire:click="sortBy('name')">Nombre</th>
                    <th class="px-4 py-3">Departamento</th>
                    <th class="px-4 py-3">Provincia</th>
                    <th class="px-4 py-3">Distrito</th>
                    <th class="px-4 py-3">CO</th>
                    <th class="px-4 py-3">Traslado</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Actualización</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($agencies as $agency)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $agency->code }}</td>
                        <td class="px-4 py-3">{{ $agency->name }}</td>
                        <td class="px-4 py-3">{{ $agency->department }}</td>
                        <td class="px-4 py-3">{{ $agency->province }}</td>
                        <td class="px-4 py-3">{{ $agency->district }}</td>
                        <td class="px-4 py-3">
                            @if($agency->is_operations_center)
                                <span class="rounded-full bg-sky-500/10 px-3 py-1 text-xs font-medium text-sky-600">Centro de Operaciones</span>
                            @else
                                <span class="text-slate-400">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($agency->has_moved)
                                <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-600">Trasladada</span>
                            @else
                                <span class="text-slate-400">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $agency->statusLabel() }}</td>
                        <td class="px-4 py-3">{{ optional($agency->updated_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2 text-xs">
                                <a class="rounded-lg border border-slate-200 px-3 py-1 dark:border-slate-700" href="{{ route('admin.agencies.show', $agency) }}">Ver</a>
                                <a class="rounded-lg border border-slate-200 px-3 py-1 dark:border-slate-700" href="{{ route('admin.agencies.edit', $agency) }}">Editar</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-slate-500">No hay agencias registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $agencies->links() }}</div>
</div>
