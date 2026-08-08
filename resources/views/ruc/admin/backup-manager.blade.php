<div class="space-y-6">
    <x-ui.page-header eyebrow="RUC" title="Gestor de Backups" subtitle="Crear, descargar y restaurar backups de la base de datos RUC con validación de integridad.">
        <x-slot:actions>
            <form id="create-backup-form" action="{{ route('admin.ruc.backups.create') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 disabled:opacity-50" id="create-backup-btn">
                    <span id="create-text">💾 Crear Backup</span>
                    <span id="create-loading" style="display: none;">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>
            </form>
            <x-ui.button wire:click="$set('show_upload', true)">
                📤 Cargar Backup
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" title="Almacenamiento de Backups">Todos los backups se almacenan en <code>storage/app/backups/ruc</code> con validación SHA-256 de integridad. Se crea un backup de seguridad automático antes de restaurar.</x-ui.alert>

    <!-- Modal de Carga -->
    <x-ui.modal :open="$show_upload" title="Cargar archivo de backup" closeLabel="Cerrar">
        <form id="backup-upload-form" action="{{ route('admin.ruc.backups.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="backup_file_input" class="block text-sm font-medium text-[color:var(--color-text-primary)] mb-2">
                    Seleccionar archivo (.sql.gz)
                </label>
                <input
                    id="backup_file_input"
                    name="backup_file"
                    type="file"
                    accept=".gz"
                    required
                    class="block w-full text-sm text-[color:var(--color-text-secondary)]
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-[var(--radius-control)]
                    file:border-0
                    file:text-sm file:font-semibold
                    file:bg-[color:var(--color-brand)]/10
                    file:text-[color:var(--color-brand)]
                    hover:file:bg-[color:var(--color-brand)]/20"
                    onchange="updateFileName(this)"
                >
                <p id="file-name-display" class="text-xs text-[color:var(--color-text-secondary)] mt-2" style="display: none;">
                    ✓ Archivo: <span id="file-name"></span> (<span id="file-size"></span>)
                </p>
            </div>

            <div class="bg-[color:var(--color-background)] p-3 rounded-[var(--radius-control)] border border-[color:var(--color-border)]">
                <p class="text-xs text-[color:var(--color-text-secondary)]">
                    📋 <strong>Requisitos:</strong> Máximo 10 MB, formato .gz de backup SQL de PostgreSQL
                </p>
            </div>

            <div class="flex gap-2 justify-end pt-4 border-t border-[color:var(--color-border)]">
                <button type="button" onclick="closeUploadModal()" class="inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-4 py-2 text-sm font-medium text-[color:var(--color-text-primary)] shadow-sm hover:bg-[color:var(--color-background)]">
                    Cancelar
                </button>
                <button type="submit" id="upload-submit-btn" class="inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-lg bg-[color:var(--color-brand)] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[color:var(--color-brand)]/90">
                    <span id="upload-text">Cargar Backup</span>
                    <span id="upload-loading" style="display: none;">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </x-ui.modal>

    <script>
        function formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            while (size > 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            return (Math.round(size * 100) / 100) + ' ' + units[unitIndex];
        }

        function updateFileName(input) {
            if (input.files && input.files[0]) {
                document.getElementById('file-name').textContent = input.files[0].name;
                document.getElementById('file-size').textContent = formatBytes(input.files[0].size);
                document.getElementById('file-name-display').style.display = 'block';
            } else {
                document.getElementById('file-name-display').style.display = 'none';
            }
        }

        function closeUploadModal() {
            @this.set('show_upload', false);
        }

        // Manejar envío de formulario de upload
        //
        // OJO: este formulario vive dentro del componente modal (ui.modal),
        // que usa Alpine x-teleport para moverlo al final del documento.
        // Mientras no se teletransporta, el contenido está dentro de un
        // template inerte y document.getElementById() no lo encuentra. Ese
        // teletransporte ocurre cuando Alpine inicializa (vía las scripts de
        // Livewire, cargadas casi al final del documento), que corre
        // DESPUÉS de este bloque de script inline.
        // Por eso NO se puede engancharlo con getElementById aquí arriba:
        // el listener nunca se registraba y el formulario terminaba
        // enviándose de forma nativa (sin preventDefault), disparando el
        // aviso del navegador de "reenviar formulario" al recargar.
        // Delegar el evento en document sí funciona sin importar cuándo
        // se inserta el nodo real en el DOM.
        document.addEventListener('submit', function(e) {
            if (e.target?.id !== 'backup-upload-form') {
                return;
            }
            e.preventDefault();
            const file = document.getElementById('backup_file_input').files[0];
            if (!file) {
                alert('Por favor selecciona un archivo');
                return;
            }

            document.getElementById('upload-text').style.display = 'none';
            document.getElementById('upload-loading').style.display = 'inline';
            document.getElementById('upload-submit-btn').disabled = true;

            const formData = new FormData(e.target);

            fetch(e.target.action, {
                method: 'POST',
                body: formData,
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Livewire.dispatch('toast', { type: 'success', message: data.message });
                    @this.set('show_upload', false);
                    @this.resetPage();
                    document.getElementById('backup-upload-form').reset();
                    document.getElementById('file-name-display').style.display = 'none';
                } else {
                    Livewire.dispatch('toast', { type: 'error', message: data.message || 'Error desconocido' });
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                Livewire.dispatch('toast', { type: 'error', message: error.message || 'Error al cargar el archivo' });
            })
            .finally(() => {
                document.getElementById('upload-text').style.display = 'inline';
                document.getElementById('upload-loading').style.display = 'none';
                document.getElementById('upload-submit-btn').disabled = false;
            });
        });

        // Manejar envío de formulario de crear backup
        document.getElementById('create-backup-form')?.addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('create-backup-btn');
            document.getElementById('create-text').style.display = 'none';
            document.getElementById('create-loading').style.display = 'inline';
            btn.disabled = true;

            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                },
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Livewire.dispatch('toast', { type: 'success', message: data.message });
                    setTimeout(() => @this.resetPage(), 500);
                } else {
                    Livewire.dispatch('toast', { type: 'error', message: data.message || 'Error desconocido' });
                }
            })
            .catch(error => {
                console.error('Create backup error:', error);
                Livewire.dispatch('toast', { type: 'error', message: error.message || 'Error al crear el backup' });
            })
            .finally(() => {
                document.getElementById('create-text').style.display = 'inline';
                document.getElementById('create-loading').style.display = 'none';
                btn.disabled = false;
            });
        });
    </script>

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
