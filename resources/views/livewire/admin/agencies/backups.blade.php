<div class="space-y-6" @if($activeRestore && $activeRestore->isRunning()) wire:poll.2s @endif>
    <x-ui.page-header title="Copias de seguridad de agencias" subtitle="Respaldo y restauración completa del padrón de agencias, separados de las exportaciones funcionales.">
        <x-slot:actions>
            <x-ui.button type="button" wire:click="create" loading-target="create">Crear copia</x-ui.button>
            <x-ui.button href="{{ route('admin.settings.agency-backups') }}" variant="secondary">Ajustes</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($integrityResult)<x-ui.alert tone="info">Integridad: {{ $integrityResult }}</x-ui.alert>@endif

    {{-- Estado de la restauración en curso o de la última ejecutada --}}
    @if($activeRestore)
        <x-ui.card padding="p-5" aria-labelledby="restore-status-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="restore-status-title" class="font-display text-lg font-semibold text-white">Restauración</h2>
                    <p class="mt-1 text-sm text-[color:var(--color-text-muted)]">
                        <span class="font-mono">{{ $activeRestore->filename }}</span>
                        · {{ $activeRestore->created_at?->timezone('America/Lima')->format('d/m/Y H:i:s') }}
                        · {{ $activeRestore->createdBy?->name ?? 'Sistema' }}
                    </p>
                </div>
                <x-ui.badge :tone="$activeRestore->status->tone()">{{ $activeRestore->status->label() }}</x-ui.badge>
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-[color:var(--color-text-muted)]">
                    <span>{{ $activeRestore->stage }}</span>
                    <span>{{ $activeRestore->progress }}%</span>
                </div>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-white/10" role="progressbar" aria-valuenow="{{ $activeRestore->progress }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full transition-all duration-500 {{ $activeRestore->status->value === 'failed' ? 'bg-rose-400' : 'bg-emerald-400' }}" style="width: {{ $activeRestore->progress }}%"></div>
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ([
                    ['Procesadas', $activeRestore->processed_records.' / '.$activeRestore->total_records, 'text-white'],
                    ['Creadas', $activeRestore->created_records, 'text-emerald-300'],
                    ['Actualizadas', $activeRestore->updated_records, 'text-sky-300'],
                    ['A papelera', $activeRestore->trashed_records, $activeRestore->trashed_records > 0 ? 'text-amber-300' : 'text-white'],
                ] as [$label, $value, $tone])
                    <div>
                        <dt class="text-xs text-[color:var(--color-text-muted)]">{{ $label }}</dt>
                        <dd class="mt-1 text-lg font-semibold {{ $tone }}">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if($activeRestore->error_message)
                <div class="mt-4"><x-ui.alert tone="danger">{{ $activeRestore->error_message }}</x-ui.alert></div>
            @endif

            @if($activeRestore->status->value === 'completed')
                <p class="mt-4 text-sm text-[color:var(--color-text-muted)]">
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
        <x-ui.card padding="p-5" aria-labelledby="restore-upload-title">
            <h2 id="restore-upload-title" class="font-display text-lg font-semibold text-white">Restaurar desde archivo</h2>
            <p class="mt-1 text-sm text-[color:var(--color-text-muted)]">
                Sube un archivo <span class="font-mono">.json</span> generado por esta misma pantalla. Antes de escribir nada se crea
                una copia de seguridad automática, y la restauración se ejecuta en segundo plano.
            </p>

            <form wire:submit="restoreFromUpload" class="mt-4 space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="agency-restore-file" class="mb-1 block text-sm font-medium text-white">Archivo de copia</label>
                        <input id="agency-restore-file" type="file" accept=".json,application/json" wire:model="restoreFile"
                               class="block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white file:mr-3 file:rounded-md file:border-0 file:bg-white/10 file:px-3 file:py-1.5 file:text-sm file:text-white" />
                        <div wire:loading wire:target="restoreFile" class="mt-1 text-xs text-[color:var(--color-text-muted)]">Subiendo archivo…</div>
                        @error('restoreFile')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>

                    <x-ui.dropdown-select id="agency-restore-mode" wire:model="restoreMode" label="Modo de restauración" :value="$restoreMode" :options="$restoreModes" />
                </div>

                @error('restoreMode')<p class="text-xs text-rose-300">{{ $message }}</p>@enderror

                <x-ui.alert tone="warning">
                    El modo <strong>réplica exacta</strong> envía a la papelera las agencias que no aparezcan en la copia.
                    Nunca se elimina nada de forma definitiva y todo puede revertirse desde la papelera.
                </x-ui.alert>

                <div class="flex justify-end">
                    <x-ui.button type="submit" loading-target="restoreFromUpload">Restaurar copia</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.table>
        <thead><tr><th class="px-5 py-4">Archivo</th><th class="px-5 py-4">Creada</th><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Agencias</th><th class="px-5 py-4">Tamaño</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">SHA-256</th><th class="px-5 py-4">Acciones</th></tr></thead>
        <tbody class="divide-y divide-white/5">
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

    {{-- Historial de restauraciones --}}
    @if($restores->isNotEmpty())
        <x-ui.card padding="p-0" aria-labelledby="restore-history-title">
            <div class="px-5 pt-5"><h2 id="restore-history-title" class="font-display text-lg font-semibold text-white">Restauraciones recientes</h2></div>
            <x-ui.table>
                <thead><tr><th class="px-5 py-4">Archivo</th><th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Modo</th><th class="px-5 py-4">Creadas</th><th class="px-5 py-4">Actualizadas</th><th class="px-5 py-4">Papelera</th><th class="px-5 py-4">Estado</th></tr></thead>
                <tbody class="divide-y divide-white/5">
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
