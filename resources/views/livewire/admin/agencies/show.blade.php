<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold">{{ $agency->name }}</h2>
                <p class="text-sm text-slate-500">{{ $agency->code }} · {{ $agency->department }} / {{ $agency->province }} / {{ $agency->district }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.agencies.edit', $agency) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm dark:border-slate-700">Editar</a>
                <a href="{{ route('admin.agencies.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm dark:border-slate-700">Volver</a>
            </div>
        </div>
    </div>

    @if ($agency->has_moved)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            <div class="font-semibold">Esta agencia se trasladó a:</div>
            @if ($agency->movedToAgency)
                <p class="mt-2">{{ $agency->movedToAgency->name }} · {{ $agency->movedToAgency->code }}</p>
                <p class="text-sm">{{ $agency->movedToAgency->address }}</p>
                <a href="{{ route('admin.agencies.show', $agency->movedToAgency) }}" class="mt-2 inline-flex text-sm underline">Ver destino</a>
            @else
                <p class="mt-2">{{ $agency->moved_to_address }}</p>
            @endif
            @if ($agency->move_notice)
                <p class="mt-2 text-sm">{{ $agency->move_notice }}</p>
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-4 md:grid-cols-2">
                    <div><span class="text-sm text-slate-500">Dirección</span><p>{{ $agency->address }}</p></div>
                    <div><span class="text-sm text-slate-500">Referencia</span><p>{{ $agency->reference ?? '—' }}</p></div>
                    <div><span class="text-sm text-slate-500">Teléfono</span><p>{{ $agency->phone ?? '—' }}</p></div>
                    <div><span class="text-sm text-slate-500">Correo</span><p>{{ $agency->email ?? '—' }}</p></div>
                    <div><span class="text-sm text-slate-500">Horario</span><p>{{ $agency->schedule ?? '—' }}</p></div>
                    <div><span class="text-sm text-slate-500">Servicios</span><p>{{ is_array($agency->services) ? implode(', ', $agency->services) : '—' }}</p></div>
                    <div><span class="text-sm text-slate-500">Centro de Operaciones</span><p>{{ $agency->is_operations_center ? 'Sí' : 'No' }}</p></div>
                    <div><span class="text-sm text-slate-500">Tamaño</span><p>{{ $agency->size?->label() ?? '—' }}</p></div>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-lg font-semibold">Historial reciente</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($history as $item)
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                            <div class="flex items-center justify-between">
                                <div class="font-medium">{{ $item->action }}</div>
                                <div class="text-xs text-slate-500">{{ $item->created_at?->format('d/m/Y H:i') }}</div>
                            </div>
                            <div class="text-sm text-slate-500">{{ $item->user_agent }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sin historial disponible.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-lg font-semibold">Estado</h3>
                <p class="mt-2">{{ $agency->statusLabel() }}</p>
                <p class="mt-1 text-sm text-slate-500">Versión {{ $agency->data_version }}</p>
                <p class="mt-1 text-sm text-slate-500">Actualizado {{ optional($agency->updated_at)->format('d/m/Y H:i') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-lg font-semibold">Ubicación</h3>
                <p class="mt-2 text-sm text-slate-500">{{ $agency->latitude && $agency->longitude ? $agency->latitude.', '.$agency->longitude : 'Sin coordenadas' }}</p>
                @if ($agency->map_url)
                    <a href="{{ $agency->map_url }}" target="_blank" class="mt-3 inline-flex text-sm underline">Abrir en Google Maps</a>
                @endif
            </div>
        </div>
    </div>
</div>
