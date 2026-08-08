    <div class="space-y-6">
        <x-ui.page-header eyebrow="RUC" title="Gestor de Backups" subtitle="Backup y restore de la tabla ruc_records. Solo datos: el schema lo controlan las migrations de Laravel." />

        <div class="grid gap-6 md:grid-cols-2">
            <x-ui.card>
                <h3 class="text-base font-semibold mb-2">Crear Backup</h3>
                <p class="text-sm text-[color:var(--color-text-secondary)] mb-4">
                    Genera un dump de <code>ruc_records</code> (solo datos) y lo guarda en el servidor.
                </p>
                {{-- Formulario HTML tradicional: POST normal del navegador, sin
                     JavaScript interceptando el submit. Laravel procesa la
                     petición y responde con un redirect. --}}
                <form method="POST" action="{{ route('admin.ruc.backups.store') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
                        💾 Crear Backup
                    </button>
                </form>
            </x-ui.card>

            <x-ui.card>
                <h3 class="text-base font-semibold mb-2">Importar Backup</h3>
                <p class="text-sm text-[color:var(--color-text-secondary)] mb-4">
                    Sube un archivo <code>.dump</code> generado por este mismo sistema (o <code>.gz</code> de versiones anteriores). Se valida el contenido, nunca la extensión.
                </p>
                <form method="POST" action="{{ route('admin.ruc.backups.import') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input
                        type="file"
                        name="backup"
                        accept=".dump,.gz"
                        required
                        class="block w-full text-sm text-[color:var(--color-text-secondary)]
                        file:mr-4 file:py-2 file:px-4 file:rounded-[var(--radius-control)] file:border-0
                        file:text-sm file:font-semibold file:bg-[color:var(--color-brand)]/10
                        file:text-[color:var(--color-brand)] hover:file:bg-[color:var(--color-brand)]/20"
                    >
                    <button type="submit" class="inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-lg bg-[color:var(--color-brand)] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[color:var(--color-brand)]/90">
                        📤 Importar Backup
                    </button>
                    <p class="text-xs text-[color:var(--color-text-secondary)]">
                        Máximo {{ number_format(config('ruc.backup.max_upload_mb')) }} MB.
                    </p>
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
                                    <span class="ml-1 text-xs text-[color:var(--color-brand)]">🛡️ seguridad</span>
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
                                        <x-ui.badge tone="success">✅ Completado</x-ui.badge>
                                        @break
                                    @case(\App\Modules\Ruc\Models\RucBackup::STATUS_CREATING)
                                        <x-ui.badge tone="warning">⏳ Creando</x-ui.badge>
                                        @break
                                    @case(\App\Modules\Ruc\Models\RucBackup::STATUS_FAILED)
                                        <x-ui.badge tone="danger">❌ Fallido</x-ui.badge>
                                        @break
                                    @default
                                        <x-ui.badge>{{ $backup->status }}</x-ui.badge>
                                @endswitch
                            </td>
                            <td class="px-6 py-4 text-sm text-[color:var(--color-text-secondary)]">
                                {{ $backup->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex flex-wrap gap-2 justify-center">
                                    @if($backup->isCompleted())
                                        <a
                                            href="{{ route('admin.ruc.backups.download', $backup) }}"
                                            class="inline-flex items-center justify-center px-2.5 py-1.5 text-xs font-medium rounded-md bg-blue-500/10 text-[color:var(--color-brand)] hover:bg-blue-500/20 transition-colors"
                                        >
                                            📥 Descargar
                                        </a>

                                        <form method="POST" action="{{ route('admin.ruc.backups.restore', $backup) }}" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                onclick="return confirm('¿Restaurar {{ number_format($backup->total_records ?? 0) }} registros? Se reemplazarán TODOS los RUC actuales. Se creará un backup de seguridad automáticamente.')"
                                                class="inline-flex items-center justify-center px-2.5 py-1.5 text-xs font-medium rounded-md bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 transition-colors"
                                            >
                                                🔄 Restaurar
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.ruc.backups.destroy', $backup) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            onclick="return confirm('¿Eliminar este backup permanentemente? Esta acción no se puede deshacer.')"
                                            class="inline-flex items-center justify-center px-2.5 py-1.5 text-xs font-medium rounded-md bg-red-500/10 text-[color:var(--color-danger)] hover:bg-red-500/20 transition-colors"
                                        >
                                            🗑️ Eliminar
                                        </button>
                                    </form>
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
