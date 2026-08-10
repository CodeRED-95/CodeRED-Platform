<div class="space-y-6" @if($activeRestore && $activeRestore->isRunning()) wire:poll.2s @endif>
    <x-ui.page-header title="Copias de seguridad de agencias" subtitle="Respaldo y restauración completa del padrón de agencias, separados de las exportaciones funcionales.">
        <x-slot:actions>
            <x-ui.button type="button" wire:click="create" loading-target="create">Crear copia</x-ui.button>
            <x-ui.button href="{{ route('admin.settings.agency-backups') }}" variant="secondary">Ajustes</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($integrityResult)
        <x-ui.alert tone="info" title="Resultado de integridad">{{ $integrityResult }}</x-ui.alert>
    @endif

    @if($activeRestore)
        <x-ui.card padding="p-5" aria-labelledby="restore-status-title">
            <x-ui.operation-status
                id="restore-status-title"
                title="Restauración"
                :status="$activeRestore->status->value === 'completed' ? 'completed' : ($activeRestore->status->value === 'failed' ? 'failed' : 'running')"
                :message="$activeRestore->filename.' · '.($activeRestore->created_at?->timezone('America/Lima')->format('d/m/Y H:i:s') ?? '') . ' · '.($activeRestore->createdBy?->name ?? 'Sistema')"
                :elapsed="$activeRestore->started_at ? $activeRestore->started_at->diffForHumans(now(), true) : null"
            >
                <x-slot:progress>
                    <x-ui.progress :value="$activeRestore->progress" label="{{ $activeRestore->stage }}" tone="{{ $activeRestore->status->value === 'failed' ? 'danger' : 'success' }}" />
                </x-slot:progress>
            </x-ui.operation-status>

            <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ([
                    ['Procesadas', $activeRestore->processed_records.' / '.$activeRestore->total_records, 'text-white'],
                    ['Creadas', $activeRestore->created_records, 'text-emerald-300'],
                    ['Actualizadas', $activeRestore->updated_records, 'text-sky-300'],
                    ['A papelera', $activeRestore->trashed_records, 'text-amber-300'],
                ] as [$label, $value, $tone])
                    <div>
                        <dt class="text-xs text-[color:var(--color-text-secondary)]">{{ $label }}</dt>
                        <dd class="mt-1 text-lg font-semibold {{ $tone }}">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if($activeRestore->error_message)
                <div class="mt-4"><x-ui.alert tone="danger">{{ $activeRestore->error_message }}</x-ui.alert></div>
            @endif

            @if($activeRestore->status->value === 'completed')
                <p class="mt-4 text-sm text-[color:var(--color-text-secondary)]">
                    Historial de nombres restaurado: {{ $activeRestore->name_histories_restored }}.
                    @if($activeRestore->safetyBackup)
                        Copia previa de seguridad: <span class="font-mono">{{ $activeRestore->safetyBackup->filename }}</span>.
                    @endif
                </p>
            @endif
        </x-ui.card>
    @endif

    {{-- Subida manual de una copia para restaurar --}}
    @if($canRestore)
        <x-ui.card title="Restaurar desde archivo" description="Sube un archivo .json generado por esta misma pantalla. Antes de escribir nada se crea una copia de seguridad automática, y la restauración se ejecuta en segundo plano.">
            <p class="text-sm text-[color:var(--color-text-secondary)]">
                Sube un archivo <span class="font-mono">.json</span> generado por esta misma pantalla. Antes de escribir nada se crea
                una copia de seguridad automática, y la restauración se ejecuta en segundo plano.
            </p>

            <form wire:submit="restoreFromUpload" class="mt-4 space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-ui.file-upload
                            id="agency-restore-file"
                            wire:model="restoreFile"
                            label="Archivo de copia"
                            accept=".json,application/json"
                            description="Selecciona el JSON de backup para iniciar la validación."
                            :error="$errors->first('restoreFile')"
                            required
                        />
                        <div wire:loading wire:target="restoreFile" class="mt-1 text-xs text-[color:var(--color-text-secondary)]">Subiendo archivo…</div>
                    </div>

                    <x-ui.dropdown-select id="agency-restore-mode" wire:model="restoreMode" label="Modo de restauración" :value="$restoreMode" :options="$restoreModes" />
                </div>

                @error('restoreMode')<p class="text-xs text-[color:var(--color-danger)]">{{ $message }}</p>@enderror

                <x-ui.alert tone="warning" title="Modo réplica exacta">
                    El modo <strong>réplica exacta</strong> envía a la papelera las agencias que no aparezcan en la copia.
                    Nunca se elimina nada de forma definitiva y todo puede revertirse desde la papelera.
                </x-ui.alert>

                <div class="flex justify-end">
                    <x-ui.button type="submit" loading-target="restoreFromUpload">Restaurar copia</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card title="Copias registradas" description="Listado de backups disponibles para descarga, verificación y restauración.">
        <x-ui.table>
        <thead><tr><th class="px-5 py-4">Archivo</th><th class="px-5 py-4">Creada</th><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Agencias</th><th class="px-5 py-4">Tamaño</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">SHA-256</th><th class="px-5 py-4">Acciones</th></tr></thead>
        <tbody>
        @forelse($backups as $backup)
            <tr>
                <td class="px-5 py-4 font-mono text-sm">{{ $backup->filename }}</td>
                <td class="px-5 py-4">{{ $backup->created_at?->timezone('America/Lima')->format('d/m/Y H:i:s') }}</td>
                <td class="px-5 py-4">{{ $backup->createdBy?->name ?? 'Sistema' }}</td>
                <td class="px-5 py-4">{{ $backup->record_count }}</td>
                <td class="px-5 py-4">{{ number_format($backup->size_bytes / 1024, 2) }} KB</td>
                <td class="px-5 py-4"><x-ui.badge :tone="$backup->status?->value === 'completed' ? 'success' : ($backup->status?->value === 'failed' ? 'danger' : 'warning')">{{ $backup->status?->value }}</x-ui.badge></td>
                <td class="max-w-48 truncate px-5 py-4 font-mono text-xs" title="{{ $backup->checksum_sha256 }}">{{ $backup->checksum_sha256 ?? '—' }}</td>
                <td class="px-5 py-4"><div class="flex flex-wrap gap-2">
                    @if($backup->status?->value === 'completed')
                        <x-ui.button href="{{ route('admin.agencies.backups.download', $backup) }}" size="sm" variant="secondary">Descargar</x-ui.button>
                        @if($canRestore)
                            <x-ui.confirm-dialog id="restore-backup-{{ $backup->id }}" title="Restaurar esta copia"
                                message="Se creará una copia de seguridad previa y la restauración se ejecutará en segundo plano. Podrás seguir el progreso en esta pantalla."
                                confirm-label="Restaurar" confirm-action="restoreFromBackup({{ $backup->id }})">
                                <x-slot:trigger><x-ui.button size="sm" variant="outline">Restaurar</x-ui.button></x-slot:trigger>
                            </x-ui.confirm-dialog>
                        @endif
                    @endif
                    <x-ui.button type="button" size="sm" variant="outline" wire:click="verifyIntegrity({{ $backup->id }})">Verificar integridad</x-ui.button>
                    <x-ui.confirm-dialog id="delete-backup-{{ $backup->id }}" title="Eliminar copia" message="Se eliminarán el archivo privado y su registro. Esta acción no puede deshacerse." confirm-label="Eliminar copia" confirm-action="delete({{ $backup->id }})"><x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar</x-ui.button></x-slot:trigger></x-ui.confirm-dialog>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-5 py-12"><x-ui.empty-state title="No existen copias" description="Crea la primera copia privada de agencias." /></td></tr>
        @endforelse
        </tbody>
        </x-ui.table>
        <x-ui.pagination :paginator="$backups" />
    </x-ui.card>

    @if($restores->isNotEmpty())
        <x-ui.card title="Restauraciones recientes" padding="p-0">
            <x-ui.table>
                <thead><tr><th class="px-5 py-4">Archivo</th><th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Modo</th><th class="px-5 py-4">Creadas</th><th class="px-5 py-4">Actualizadas</th><th class="px-5 py-4">Papelera</th><th class="px-5 py-4">Estado</th></tr></thead>
                <tbody>
                @foreach($restores as $restore)
                    <tr>
                        <td class="px-5 py-4 font-mono text-sm">{{ $restore->filename }}</td>
                        <td class="px-5 py-4">{{ $restore->created_at?->timezone('America/Lima')->format('d/m/Y H:i:s') }}</td>
                        <td class="px-5 py-4">{{ $restore->createdBy?->name ?? 'Sistema' }}</td>
                        <td class="px-5 py-4">{{ $restore->mode === 'replace' ? 'Réplica exacta' : 'Combinar' }}</td>
                        <td class="px-5 py-4">{{ $restore->created_records }}</td>
                        <td class="px-5 py-4">{{ $restore->updated_records }}</td>
                        <td class="px-5 py-4">{{ $restore->trashed_records }}</td>
                        <td class="px-5 py-4"><x-ui.badge :tone="$restore->status->tone()">{{ $restore->status->label() }}</x-ui.badge></td>
                    </tr>
                @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
