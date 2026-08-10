<div class="space-y-8">
    <x-ui.page-header title="Instalación Shalom Recordar" subtitle="Detalle de la instalación y sus registros." />
    <x-ui.card>
        <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Usuario</dt><dd class="mt-1 font-medium">{{ $installation->user->name }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">UUID</dt><dd class="mt-1 font-mono text-xs">{{ $installation->installation_uuid }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Versión</dt><dd class="mt-1 font-medium">{{ $installation->extension_version }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Última sincronización</dt><dd class="mt-1 font-medium">{{ $installation->last_synced_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        </dl>
    </x-ui.card>
    <x-ui.card><x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar registros" placeholder="Campo o valor" /></x-ui.card>
    <x-ui.table id="shalom-recordar-installation-records">
        <thead><tr><th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Campo</th><th class="px-5 py-4">Valor</th></tr></thead>
        <tbody class="divide-y divide-white/5">
        @forelse ($records as $record)
            <tr>
                <td class="px-5 py-4">{{ $record->recorded_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-5 py-4">{{ $record->field }}</td>
                <td class="px-5 py-4">{{ $record->value }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-5 py-12"><x-ui.empty-state title="Sin registros" description="La instalación todavía no tiene datos sincronizados." icon="⌁" /></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
    <x-ui.pagination :paginator="$records" />
</div>
