<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-semibold">Agencias Shalom</h2>
                <p class="text-sm text-slate-500">Administración inicial del módulo Agencies.</p>
            </div>
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar agencia..." class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 lg:w-80">
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-950">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Departamento</th>
                    <th class="px-4 py-3">Provincia</th>
                    <th class="px-4 py-3">Distrito</th>
                    <th class="px-4 py-3">CO</th>
                    <th class="px-4 py-3">Traslado</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Actualización</th>
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
                        <td class="px-4 py-3">{{ $agency->status?->label() ?? $agency->status }}</td>
                        <td class="px-4 py-3">{{ optional($agency->updated_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-slate-500">No hay agencias registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $agencies->links() }}</div>
</div>
