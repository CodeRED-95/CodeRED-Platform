<div class="space-y-8">
    <x-ui.page-header title="Shalom Recordar" subtitle="Consulta sincronizaciones por usuario e instalación." />
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Usuarios con datos" :value="$stats['users']" tone="brand" />
        <x-ui.stat-card label="Instalaciones" :value="$stats['installations']" tone="info" />
        <x-ui.stat-card label="Registros" :value="$stats['records']" tone="success" />
        <x-ui.stat-card label="Sincronizaciones recientes" :value="$stats['recent_syncs']" tone="warning" />
    </div>
    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-2">
            <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="Usuario o correo" />
            <x-ui.dropdown-select id="shalom-recordar-per-page" wire:model.live="perPage" label="Por página" :value="$perPage" :options="[15 => '15', 30 => '30', 50 => '50']" />
        </div>
    </x-ui.card>
    <x-ui.table id="shalom-recordar-users">
        <thead><tr><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Instalaciones</th><th class="px-5 py-4">Registros</th><th class="px-5 py-4">Última sincronización</th><th class="px-5 py-4">Acciones</th></tr></thead>
        <tbody class="divide-y divide-white/5">
        @forelse ($users as $user)
            <tr>
                <td class="px-5 py-4"><div class="font-medium">{{ $user->name }}</div><div class="text-sm text-[color:var(--color-text-secondary)]">{{ $user->email }}</div></td>
                <td class="px-5 py-4">{{ $user->shalom_recordar_installations_count }}</td>
                <td class="px-5 py-4">{{ $user->shalom_recordar_records_count }}</td>
                <td class="px-5 py-4">{{ $user->updated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-5 py-4"><x-ui.button href="{{ route('admin.shalom-recordar.users.show', $user) }}" size="sm" variant="outline">Ver</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-12"><x-ui.empty-state title="Sin datos sincronizados" description="Cuando la extensión sincronice, los usuarios aparecerán aquí." icon="⌁" /></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
    <x-ui.pagination :paginator="$users" scroll-to="#shalom-recordar-users" />
</div>
