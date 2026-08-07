<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold">Gestor de Backups RUC</h2>
        <button wire:click="$set('show_upload', true)"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            📤 Cargar Backup
        </button>
    </div>

    <!-- Cargar Backup Modal -->
    @if($show_upload)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-96">
            <h3 class="text-lg font-bold mb-4">Cargar archivo de backup</h3>

            <form wire:submit="uploadBackup" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Archivo (.sql.gz)</label>
                    <input wire:model.live="backup_file" type="file" accept=".gz"
                           class="w-full border rounded px-3 py-2">
                    @error('backup_file')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-2 justify-end">
                    <button type="button" wire:click="$set('show_upload', false)"
                            class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            {{ $loading ? 'disabled' : '' }}
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                        <span wire:loading.remove>Cargar</span>
                        <span wire:loading>Cargando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Filtro -->
    <div class="mb-4">
        <select wire:model.change="status_filter"
                class="px-3 py-2 border rounded">
            <option value="">Todos</option>
            <option value="completed">✅ Completados</option>
            <option value="pending">⏳ Pendientes</option>
            <option value="failed">❌ Fallidos</option>
        </select>
    </div>

    <!-- Tabla de Backups -->
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border p-3 text-left">Nombre</th>
                    <th class="border p-3 text-left">Tipo</th>
                    <th class="border p-3 text-right">Tamaño</th>
                    <th class="border p-3 text-center">Estado</th>
                    <th class="border p-3 text-left">Creado</th>
                    <th class="border p-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups['data'] as $backup)
                <tr class="border-b hover:bg-gray-50">
                    <td class="border p-3 text-sm">{{ $backup['name'] }}</td>
                    <td class="border p-3 text-sm">
                        @match($backup['backup_type'])
                            'full' => '📦 Completo',
                            'uploaded' => '📤 Cargado',
                            'safety_before_restore' => '🛡️ Seguridad',
                            default => $backup['backup_type'],
                        @endmatch
                    </td>
                    <td class="border p-3 text-right text-sm">
                        {{ $this->formatBytes($backup['file_size_bytes']) }}
                    </td>
                    <td class="border p-3 text-center">
                        @match($backup['status'])
                            'completed' => '<span class="text-green-600">✅ Completado</span>',
                            'pending' => '<span class="text-yellow-600">⏳ Pendiente</span>',
                            'failed' => '<span class="text-red-600">❌ Fallido</span>',
                            'deleted' => '<span class="text-gray-400">🗑️ Eliminado</span>',
                            default => $backup['status'],
                        @endmatch
                    </td>
                    <td class="border p-3 text-sm">
                        {{ \Carbon\Carbon::parse($backup['created_at'])->format('Y-m-d H:i') }}
                    </td>
                    <td class="border p-3 text-center">
                        @if($backup['status'] === 'completed')
                        <button wire:click="download({{ $backup['id'] }})"
                                class="text-blue-600 hover:underline">
                            📥 Descargar
                        </button>
                        <button wire:click="restoreBackup({{ $backup['id'] }})"
                                wire:loading.attr="disabled"
                                {{ $loading ? 'disabled' : '' }}
                                onclick="return confirm('⚠️ Esto restaurará los datos. ¿Continuar?')"
                                class="text-green-600 hover:underline ml-2 disabled:opacity-50">
                            🔄 Restaurar
                        </button>
                        @endif
                        <button wire:click="deleteBackup({{ $backup['id'] }})"
                                onclick="return confirm('¿Eliminar este backup?')"
                                class="text-red-600 hover:underline ml-2">
                            🗑️ Eliminar
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="border p-3 text-center text-gray-500">
                        No hay backups
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    @if($backups['last_page'] > 1)
    <div class="mt-4 flex justify-center gap-2">
        @for($p = 1; $p <= $backups['last_page']; $p++)
        <button wire:click="$set('page', {{ $p }})"
                class="{{ $page == $p ? 'bg-blue-600 text-white' : 'bg-gray-300' }} px-3 py-1 rounded">
            {{ $p }}
        </button>
        @endfor
    </div>
    @endif
</div>

@script
<script>
    Livewire.on('notify', (event) => {
        const { type, message } = event[0];
        const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';

        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${bgColor} text-white px-4 py-2 rounded shadow-lg`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => notification.remove(), 3000);
    });
</script>
@endscript
