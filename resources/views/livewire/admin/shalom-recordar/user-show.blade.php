<div class="space-y-8">
    <x-ui.page-header title="Shalom Recordar · {{ $user->name }}" subtitle="Instalaciones y registros sincronizados por este usuario." />
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.stat-card label="Instalaciones" :value="$installations->count()" tone="brand" />
        <x-ui.stat-card label="Registros" :value="$records->total()" tone="info" />
        <x-ui.stat-card label="Instalaciones con sync" :value="$installations->whereNotNull('last_synced_at')->count()" tone="success" description="Revisa el detalle de cada instalación para la fecha exacta." />
    </div>
    <x-ui.card><x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar registros" placeholder="Campo o valor" /></x-ui.card>
    <x-ui.table id="shalom-recordar-installations">
        <thead><tr><th class="px-5 py-4">UUID</th><th class="px-5 py-4">Versión</th><th class="px-5 py-4">Última sincronización</th><th class="px-5 py-4">Registros</th><th class="px-5 py-4">Acciones</th></tr></thead>
        <tbody class="divide-y divide-white/5">
        @forelse ($installations as $installation)
            <tr>
                <td class="px-5 py-4 font-mono text-xs">{{ $installation->installation_uuid }}</td>
                <td class="px-5 py-4">{{ $installation->extension_version }}</td>
                <td class="px-5 py-4">{{ $installation->last_synced_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-5 py-4">{{ $installation->records_count }}</td>
                <td class="px-5 py-4"><x-ui.button size="sm" variant="outline" href="{{ route('admin.shalom-recordar.installations.show', $installation) }}">Ver</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-12"><x-ui.empty-state title="Sin instalaciones" description="Esta cuenta todavía no ha sincronizado ninguna instalación." icon="⌁" /></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
    <x-ui.table id="shalom-recordar-records">
        <thead><tr><th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Instalación</th><th class="px-5 py-4">Campo</th><th class="px-5 py-4">Valor</th></tr></thead>
        <tbody class="divide-y divide-white/5">
        @forelse ($records as $record)
            <tr>
                <td class="px-5 py-4">{{ $record->recorded_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-5 py-4 font-mono text-xs">{{ $record->installation_uuid }}</td>
                <td class="px-5 py-4">{{ $record->field }}</td>
                <td class="px-5 py-4">{{ $record->value }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-5 py-12"><x-ui.empty-state title="Sin registros" description="No hay coincidencias para los filtros actuales." icon="⌁" /></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
    <x-ui.pagination :paginator="$records" />
</div>
