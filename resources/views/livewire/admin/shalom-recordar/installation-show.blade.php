<div class="space-y-8">
    <x-ui.page-header title="Instalación Shalom Recordar" subtitle="Detalle de la instalación y sus lotes de sincronización." />

    <x-ui.card>
        <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Usuario</dt><dd class="mt-1 font-medium">{{ $installation->user->name }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">UUID</dt><dd class="mt-1 font-mono text-xs">{{ $installation->installation_uuid }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Versión</dt><dd class="mt-1 font-medium">{{ $installation->extension_version }}</dd></div>
            <div><dt class="text-sm text-[color:var(--color-text-secondary)]">Última sincronización</dt><dd class="mt-1 font-medium">{{ $installation->last_synced_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        </dl>
        @if ($canManage)
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
        @endif
    </x-ui.card>

    {{-- Lotes de sincronización con filtro por fecha --}}
    <x-ui.card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-medium">Lotes de sincronización</p>
                <p class="text-sm text-[color:var(--color-text-secondary)]">Consulta, exporta o elimina cada lote de forma independiente.</p>
            </div>
            <div class="flex items-end gap-2">
                <x-ui.input type="date" wire:model.live="date" label="Filtrar por fecha" id="shalom-batch-date" class="w-44" />
                @if ($date !== '')
                    <x-ui.button variant="outline" size="sm" wire:click="clearDate">Limpiar filtro</x-ui.button>
                @endif
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <span class="text-sm text-[color:var(--color-text-secondary)]">{{ $batches->count() }} {{ Str::plural('lote', $batches->count()) }}</span>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-[color:var(--color-text-muted)]">
                    <tr>
                        <th class="p-3">Lote</th>
                        <th class="p-3">Registros</th>
                        <th class="p-3">Rango</th>
                        <th class="p-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($batches as $batch)
                        <tr @class(['bg-white/5' => $batch->batch_id === $this->batch])>
                            <td class="p-3 font-mono text-xs">{{ Str::limit($batch->batch_id, 32) }}</td>
                            <td class="p-3">{{ $batch->records_count }}</td>
                            <td class="p-3 text-xs text-[color:var(--color-text-secondary)]">
                                {{ \Illuminate\Support\Carbon::parse($batch->first_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="p-3">
                                @php($batchKey = base64_encode((string) $batch->batch_id))
                                <div class="flex flex-wrap justify-end gap-2">
                                    {{-- La clave va en base64 (sin comillas ni espacios), interpolada con
                                         {{ }} porque la directiva @js NO se compila dentro de los atributos
                                         de un componente y llegaría literal a Livewire, rompiendo el click. --}}
                                    <x-ui.button size="sm" variant="outline" wire:click="viewBatch('{{ $batchKey }}')">Ver lote</x-ui.button>
                                    @if ($canManage)
                                        <x-ui.button size="sm" variant="secondary" wire:click="exportBatch('{{ $batchKey }}')">Exportar Excel</x-ui.button>
                                        <x-ui.confirm-dialog
                                            id="delete-batch-{{ md5((string) $batch->batch_id) }}"
                                            title="Eliminar lote"
                                            message="Se eliminarán {{ $batch->records_count }} registros de este lote. Esta acción no se puede deshacer."
                                            confirm-label="Eliminar lote"
                                            confirm-action="deleteSyncBatch('{{ $batchKey }}')">
                                            <x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar lote</x-ui.button></x-slot:trigger>
                                        </x-ui.confirm-dialog>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-8 text-center text-[color:var(--color-text-muted)]">{{ $date !== '' ? 'No hay lotes para la fecha seleccionada.' : 'No hay lotes de sincronización.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    {{-- Detalle del lote seleccionado: solo sus registros --}}
    @if ($batchRecords !== null)
        <x-ui.card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-medium">Detalle del lote</p>
                    <p class="font-mono text-xs text-[color:var(--color-text-secondary)]">{{ Str::limit($this->batch, 48) }}</p>
                </div>
                <x-ui.button variant="outline" size="sm" wire:click="closeBatch">Cerrar detalle</x-ui.button>
            </div>

            <div class="mt-4">
                <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar en el lote" placeholder="Campo o valor" />
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm" id="shalom-recordar-batch-records">
                    <thead class="text-[color:var(--color-text-muted)]"><tr><th class="p-3">Fecha</th><th class="p-3">Campo</th><th class="p-3">Valor</th></tr></thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($batchRecords as $record)
                            <tr>
                                <td class="p-3">{{ $record->recorded_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="p-3">{{ $record->field }}</td>
                                <td class="p-3">{{ $record->value }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-8"><x-ui.empty-state title="Sin registros" description="No hay coincidencias en este lote para la búsqueda actual." icon="⌁" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4"><x-ui.pagination :paginator="$batchRecords" /></div>
        </x-ui.card>
    @endif
</div>
