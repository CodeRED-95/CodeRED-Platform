<div class="space-y-6">
    <x-ui.page-header eyebrow="RUC" title="Gestor de Backups" subtitle="Crear, descargar y restaurar backups de la base de datos RUC con validación de integridad.">
        <x-slot:actions>
            <x-ui.button wire:click="$set('show_upload', true)" loading-target="uploadBackup">
                📤 Cargar Backup
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" title="Almacenamiento de Backups">Todos los backups se almacenan en <code>storage/app/backups/ruc</code> con validación SHA-256 de integridad. Se crea un backup de seguridad automático antes de restaurar.</x-ui.alert>

    <!-- Modal de Carga -->
    <x-ui.modal :open="$show_upload" title="Cargar archivo de backup" closeLabel="Cerrar">
        <form wire:submit="uploadBackup" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-[color:var(--color-text-primary)] mb-2">
                    Seleccionar archivo (.sql.gz)
                </label>
                <input
                    wire:model.live="backup_file"
                    type="file"
                    accept=".gz"
                    class="block w-full text-sm text-[color:var(--color-text-secondary)]
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-[var(--radius-control)]
                    file:border-0
                    file:text-sm file:font-semibold
                    file:bg-[color:var(--color-brand)]/10
                    file:text-[color:var(--color-brand)]
                    hover:file:bg-[color:var(--color-brand)]/20"
                >
                @error('backup_file')
                    <p class="text-[color:var(--color-danger)] text-sm mt-2">{{ $message }}</p>
                @enderror
                @if($backup_file)
                    <p class="text-xs text-[color:var(--color-text-secondary)] mt-2">
                        ✓ Archivo: {{ $backup_file->getClientOriginalName() }} ({{ $this->formatBytes($backup_file->getSize()) }})
                    </p>
                @endif
            </div>

            <div class="bg-[color:var(--color-background)] p-3 rounded-[var(--radius-control)] border border-[color:var(--color-border)]">
                <p class="text-xs text-[color:var(--color-text-secondary)]">
                    📋 <strong>Requisitos:</strong> Máximo 10 MB, formato .gz de backup SQL de PostgreSQL
                </p>
            </div>

            <div class="flex gap-2 justify-end pt-4 border-t border-[color:var(--color-border)]">
                <x-ui.button type="button" variant="secondary" wire:click="$set('show_upload', false)">
                    Cancelar
                </x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Cargar Backup</span>
                    <span wire:loading>Cargando...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Filtros -->
    <x-ui.card class="p-0">
        <div class="border-b border-[color:var(--color-border)] p-4">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <x-ui.dropdown-select
                    id="backup-status-filter"
                    wire:model.live="status_filter"
                    label="Estado"
                    :value="$status_filter"
                    :options="[
                        '' => 'Todos',
                        'pending' => '⏳ Pendiente',
                        'completed' => '✅ Completado',
                        'failed' => '❌ Fallido',
                        'deleted' => '🗑️ Eliminado',
                    ]"
                />
            </div>
        </div>

        <!-- Tabla de Backups -->
        <div wire:loading.delay class="p-4">
            <x-ui.skeleton variant="table" :rows="5" />
        </div>

        <x-ui.table wire:loading.class="opacity-50">
            <thead class="bg-white/5">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider">Tamaño</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Registros</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Creado</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($backups as $backup)
                    <tr wire:key="backup-{{ $backup->id }}">
                        <td class="px-6 py-4 text-sm font-mono">
                            <span class="text-[color:var(--color-text-secondary)]">{{ $backup->name }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @match($backup->backup_type)
                                'full' => <span class="text-[color:var(--color-info)]">📦 Completo</span>,
                                'uploaded' => <span class="text-[color:var(--color-success)]">📤 Cargado</span>,
                                'incremental' => <span class="text-[color:var(--color-warning)]">➕ Incremental</span>,
                                'safety_before_restore' => <span class="text-[color:var(--color-brand)]">🛡️ Seguridad</span>,
                                default => <span>{{ $backup->backup_type }}</span>,
                            @endmatch
                        </td>
                        <td class="px-6 py-4 text-sm text-right">{{ $this->formatBytes($backup->file_size_bytes) }}</td>
                        <td class="px-6 py-4 text-sm text-center">
                            @if($backup->total_records)
                                <span class="text-[color:var(--color-text-secondary)]">{{ number_format($backup->total_records) }}</span>
                            @else
                                <span class="text-[color:var(--color-text-muted)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @switch($backup->status)
                                @case('completed')
                                    <x-ui.badge tone="success">✅ Completado</x-ui.badge>
                                    @break
                                @case('pending')
                                    <x-ui.badge tone="warning">⏳ Pendiente</x-ui.badge>
                                    @break
                                @case('failed')
                                    <x-ui.badge tone="danger">❌ Fallido</x-ui.badge>
                                    @break
                                @case('deleted')
                                    <x-ui.badge tone="neutral">🗑️ Eliminado</x-ui.badge>
                                    @break
                                @default
                                    <x-ui.badge>{{ $backup->status }}</x-ui.badge>
                            @endswitch
                        </td>
                        <td class="px-6 py-4 text-sm text-[color:var(--color-text-secondary)]">
                            {{ $backup->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex flex-wrap gap-1 justify-center">
                                @if($backup->status === 'completed')
                                    <button
                                        wire:click="download({{ $backup->id }})"
                                        class="inline-flex items-center justify-center px-2.5 py-1.5 text-xs font-medium rounded-md bg-blue-500/10 text-[color:var(--color-brand)] hover:bg-blue-500/20 transition-colors"
                                        title="Descargar"
                                    >
                                        📥
                                    </button>

                                    <button
                                        wire:click="restoreBackup({{ $backup->id }})"
                                        wire:confirm="⚠️ Restaurar {{ number_format($backup->total_records) }} registros. Se creará un backup de seguridad automáticamente. ¿Continuar?"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center justify-center px-2.5 py-1.5 text-xs font-medium rounded-md bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 transition-colors disabled:opacity-50"
                                        title="Restaurar"
                                    >
                                        🔄
                                    </button>
                                @endif

                                <button
                                    wire:click="deleteBackup({{ $backup->id }})"
                                    wire:confirm="🗑️ Eliminar este backup permanentemente. Esta acción no se puede deshacer. ¿Continuar?"
                                    class="inline-flex items-center justify-center px-2.5 py-1.5 text-xs font-medium rounded-md bg-red-500/10 text-[color:var(--color-danger)] hover:bg-red-500/20 transition-colors"
                                    title="Eliminar"
                                >
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8">
                            <x-ui.empty-state
                                title="No hay backups"
                                description="No hay backups disponibles con el filtro seleccionado. Crea uno nuevo usando el botón 'Cargar Backup' arriba."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <!-- Paginación -->
        @if($backups->hasPages())
            <div class="border-t border-[color:var(--color-border)] p-4">
                {{ $backups->links() }}
            </div>
        @endif
    </x-ui.card>
</div>
