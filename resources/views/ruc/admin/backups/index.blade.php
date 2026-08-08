    <div class="space-y-6">
        <x-ui.page-header eyebrow="RUC" title="Gestor de Backups" subtitle="Backup y restore de la tabla ruc_records. Solo datos: el schema lo controlan las migrations de Laravel." />

        <div class="grid gap-6 md:grid-cols-2">
            <x-ui.card>
                <h3 class="text-base font-semibold mb-2">Crear Backup</h3>
                <p class="text-sm text-[color:var(--color-text-secondary)] mb-4">
                    Genera un dump de <code>ruc_records</code> (solo datos) y lo guarda en el servidor.
                </p>

                <dl class="mb-4 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-[color:var(--color-text-secondary)]">Registros actuales</dt>
                        <dd class="font-medium">{{ number_format($currentRecordCount) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-[color:var(--color-text-secondary)]">Último backup</dt>
                        <dd>{{ $lastBackup?->created_at->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>

                {{-- Formulario HTML tradicional: POST normal del navegador, sin
                     JavaScript interceptando el submit. Laravel procesa la
                     petición y responde con un redirect. El único JS es
                     cosmético (deshabilitar el botón para evitar doble
                     submit durante una operación que puede tardar minutos). --}}
                <form method="POST" action="{{ route('admin.ruc.backups.store') }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
                    @csrf
                    <x-ui.button type="submit" variant="success" x-bind:disabled="submitting">
                        <span x-show="!submitting" class="inline-flex items-center gap-2"><x-ui.icon name="backup" class="size-4" /> Crear Backup</span>
                        <span x-show="submitting" x-cloak class="inline-flex items-center gap-2"><x-ui.spinner size="sm" /> Creando backup…</span>
                    </x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card>
                <h3 class="text-base font-semibold mb-2">Importar Backup</h3>
                <p class="text-sm text-[color:var(--color-text-secondary)] mb-4">
                    Sube un archivo <code>.dump</code> generado por este mismo sistema (o <code>.gz</code> de versiones anteriores). Se valida el contenido, nunca la extensión.
                </p>
                <form method="POST" action="{{ route('admin.ruc.backups.import') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ submitting: false }" x-on:submit="submitting = true">
                    @csrf
                    <x-ui.file-dropzone
                        name="backup"
                        label="Archivo de Backup"
                        accept=".dump,.gz"
                        required
                        help="Archivos .dump / backups legacy .sql.gz"
                        max-size="{{ number_format(config('ruc.backup.max_upload_mb')) }} MB"
                        :error="$errors->first('backup')"
                    />
                    <x-ui.button type="submit" variant="primary" x-bind:disabled="submitting">
                        <span x-show="!submitting" class="inline-flex items-center gap-2"><x-ui.icon name="upload" class="size-4" /> Importar Backup</span>
                        <span x-show="submitting" x-cloak class="inline-flex items-center gap-2"><x-ui.spinner size="sm" /> Importando…</span>
                    </x-ui.button>
                </form>
            </x-ui.card>
        </div>

        <x-ui.card class="p-0">
            <div class="p-4 border-b border-[color:var(--color-border)]">
                <h3 class="text-base font-semibold">Backups disponibles</h3>
            </div>

            <x-ui.table>
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Nombre</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider">Tamaño</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Registros</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Fecha</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($backups as $backup)
                        <tr>
                            <td class="px-6 py-4 text-sm font-mono">
                                <span class="text-[color:var(--color-text-secondary)]">{{ $backup->name }}</span>
                                @if($backup->backup_type === \App\Modules\Ruc\Models\RucBackup::TYPE_SAFETY)
                                    <x-ui.badge tone="warning" class="ml-1">
                                        <x-ui.icon name="shield" class="size-3" /> <span class="ml-1">seguridad</span>
                                    </x-ui.badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-right">{{ $backup->formattedSize() }}</td>
                            <td class="px-6 py-4 text-sm text-center">
                                @if($backup->total_records !== null)
                                    {{ number_format($backup->total_records) }}
                                @else
                                    <span class="text-[color:var(--color-text-muted)]">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @switch($backup->status)
                                    @case(\App\Modules\Ruc\Models\RucBackup::STATUS_COMPLETED)
                                        <x-ui.badge tone="success">Completado</x-ui.badge>
                                        @break
                                    @case(\App\Modules\Ruc\Models\RucBackup::STATUS_CREATING)
                                        <x-ui.badge tone="info">Creando</x-ui.badge>
                                        @break
                                    @case(\App\Modules\Ruc\Models\RucBackup::STATUS_FAILED)
                                        <x-ui.badge tone="danger">Fallido</x-ui.badge>
                                        @break
                                    @default
                                        <x-ui.badge tone="neutral">{{ $backup->status }}</x-ui.badge>
                                @endswitch
                            </td>
                            <td class="px-6 py-4 text-sm text-[color:var(--color-text-secondary)]">
                                {{ $backup->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex flex-wrap gap-2 justify-center">
                                    @if($backup->isCompleted())
                                        <x-ui.button href="{{ route('admin.ruc.backups.download', $backup) }}" size="sm" variant="secondary">
                                            <x-ui.icon name="download" class="size-4" /> Descargar
                                        </x-ui.button>

                                        <form id="restore-form-{{ $backup->id }}" method="POST" action="{{ route('admin.ruc.backups.restore', $backup) }}" class="inline">
                                            @csrf
                                        </form>
                                        <x-ui.confirm-dialog
                                            id="restore-dialog-{{ $backup->id }}"
                                            tone="warning"
                                            icon="restore"
                                            title="Restaurar backup"
                                            confirm-label="Restaurar Backup"
                                            cancel-label="Cancelar"
                                            form="restore-form-{{ $backup->id }}"
                                            loading-label="Iniciando restauración…"
                                        >
                                            <x-slot:trigger>
                                                <x-ui.button type="button" size="sm" variant="warning">
                                                    <x-ui.icon name="restore" class="size-4" /> Restaurar
                                                </x-ui.button>
                                            </x-slot:trigger>

                                            <p>Esta acción reemplazará todos los registros actuales de RUC por los contenidos en este backup.</p>
                                            <dl class="mt-4 space-y-2">
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-[color:var(--color-text-secondary)]">Backup</dt>
                                                    <dd class="break-all text-right font-mono text-xs">{{ $backup->name }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-[color:var(--color-text-secondary)]">Registros del backup</dt>
                                                    <dd class="font-medium text-[color:var(--color-text-primary)]">{{ number_format($backup->total_records ?? 0) }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-[color:var(--color-text-secondary)]">Registros actuales</dt>
                                                    <dd class="font-medium text-[color:var(--color-text-primary)]">{{ number_format($currentRecordCount) }}</dd>
                                                </div>
                                            </dl>
                                            <x-ui.alert tone="warning" class="mt-4">
                                                Antes de continuar se creará automáticamente un <strong>Safety Backup</strong> del estado actual. El proceso puede tardar varios minutos.
                                            </x-ui.alert>
                                        </x-ui.confirm-dialog>
                                    @endif

                                    <form id="delete-form-{{ $backup->id }}" method="POST" action="{{ route('admin.ruc.backups.destroy', $backup) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <x-ui.confirm-dialog
                                        id="delete-dialog-{{ $backup->id }}"
                                        tone="danger"
                                        icon="trash"
                                        title="Eliminar backup"
                                        confirm-label="Eliminar"
                                        cancel-label="Cancelar"
                                        form="delete-form-{{ $backup->id }}"
                                        loading-label="Eliminando…"
                                    >
                                        <x-slot:trigger>
                                            <x-ui.button type="button" size="sm" variant="danger">
                                                <x-ui.icon name="trash" class="size-4" /> Eliminar
                                            </x-ui.button>
                                        </x-slot:trigger>

                                        <p>El archivo y su registro serán eliminados permanentemente.</p>
                                        <dl class="mt-4 space-y-2">
                                            <div class="flex justify-between gap-4">
                                                <dt class="text-[color:var(--color-text-secondary)]">Nombre</dt>
                                                <dd class="break-all text-right font-mono text-xs">{{ $backup->name }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-4">
                                                <dt class="text-[color:var(--color-text-secondary)]">Tamaño</dt>
                                                <dd class="font-medium text-[color:var(--color-text-primary)]">{{ $backup->formattedSize() }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-4">
                                                <dt class="text-[color:var(--color-text-secondary)]">Fecha</dt>
                                                <dd class="font-medium text-[color:var(--color-text-primary)]">{{ $backup->created_at->format('d/m/Y H:i') }}</dd>
                                            </div>
                                        </dl>
                                    </x-ui.confirm-dialog>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8">
                                <x-ui.empty-state
                                    title="No hay backups"
                                    description="Crea uno nuevo con el botón 'Crear Backup' o importa uno existente arriba."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>

            @if($backups->hasPages())
                <div class="border-t border-[color:var(--color-border)] p-4">
                    {{ $backups->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>
