<div class="space-y-8">
    <x-ui.page-header title="Instalación Shalom Recordar" subtitle="Detalle de la instalación y sus registros." />
    <x-ui.card>
        <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Usuario</dt><dd class="mt-1 font-medium">{{ $installation->user->name }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">UUID</dt><dd class="mt-1 font-mono text-xs">{{ $installation->installation_uuid }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Versión</dt><dd class="mt-1 font-medium">{{ $installation->extension_version }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Última sincronización</dt><dd class="mt-1 font-medium">{{ $installation->last_synced_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        </dl>
        <div class="mt-6 flex flex-wrap gap-2">
            <x-ui.confirm-dialog id="installation-delete-syncs" title="Eliminar sincronizaciones" message="Se eliminarán todas las sincronizaciones de esta instalación. La cuenta y la instalación se conservarán." confirm-label="Eliminar sincronizaciones" confirm-action="deleteInstallationSyncs">
                <x-slot:trigger><x-ui.button variant="danger">Eliminar sincronizaciones</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>
            <x-ui.confirm-dialog id="installation-revoke-token" title="Revocar token" message="La instalación dejará de sincronizar hasta que se emita un nuevo token." confirm-label="Revocar token" confirm-action="revokeInstallationToken">
                <x-slot:trigger><x-ui.button variant="danger">Revocar token</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>
            <x-ui.confirm-dialog id="installation-delete" title="Eliminar instalación" message="Se eliminarán la instalación, sus sincronizaciones y su token asociado. La cuenta del usuario no se borrará." confirm-label="Eliminar instalación" confirm-action="deleteInstallation">
                <x-slot:trigger><x-ui.button variant="danger">Eliminar instalación</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>
        </div>
    </x-ui.card>
    <x-ui.card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-medium">Lotes de sincronización</p>
                <p class="text-sm text-[color:var(--color-text-secondary)]">Cada lote puede eliminarse de forma independiente.</p>
            </div>
            <span class="text-sm text-[color:var(--color-text-secondary)]">{{ $batches->count() }} lotes</span>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-[color:var(--color-text-muted)]"><tr><th class="p-3">Lote</th><th class="p-3">Registros</th><th class="p-3">Acciones</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($batches as $batch)
                        <tr>
                            <td class="p-3 font-mono text-xs">{{ $batch->batch_id }}</td>
                            <td class="p-3">{{ $batch->records_count }}</td>
                            <td class="p-3">
                                <x-ui.confirm-dialog id="delete-batch-{{ \Illuminate\Support\Str::slug((string) $batch->batch_id) }}" title="Eliminar lote" message="Se eliminarán {{ $batch->records_count }} registros de este lote." confirm-label="Eliminar lote" confirm-action="deleteSyncBatch(@js($batch->batch_id))">
                                    <x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar lote</x-ui.button></x-slot:trigger>
                                </x-ui.confirm-dialog>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-8 text-center text-[color:var(--color-text-muted)]">No hay lotes de sincronización.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
    <x-ui.card><x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar registros" placeholder="Campo o valor" /></x-ui.card>
    <x-ui.table id="shalom-recordar-installation-records">
        <thead><tr><th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Campo</th><th class="px-5 py-4">Valor</th><th class="px-5 py-4">Lote</th></tr></thead>
        <tbody class="divide-y divide-white/5">
        @forelse ($records as $record)
            <tr>
                <td class="px-5 py-4">{{ $record->recorded_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-5 py-4">{{ $record->field }}</td>
                <td class="px-5 py-4">{{ $record->value }}</td>
                <td class="px-5 py-4 font-mono text-xs">{{ $record->sync_batch_id ?? $record->sync_cursor ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-5 py-12"><x-ui.empty-state title="Sin registros" description="La instalación todavía no tiene datos sincronizados." icon="⌁" /></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
    <x-ui.pagination :paginator="$records" />
</div>
