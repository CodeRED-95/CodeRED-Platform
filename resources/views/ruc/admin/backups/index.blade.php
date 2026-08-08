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

            <x-ui.card x-data="{ importTab: 'complete' }">
                <h3 class="text-base font-semibold mb-2">Importar Backup</h3>
                <p class="text-sm text-[color:var(--color-text-secondary)] mb-4">
                    Puedes importar un backup completo (<code>.dump</code> / <code>.gz</code>)
                    o un backup multipart generado por RUC Tools (<code>manifest.json</code> + archivos <code>.partXXXX</code>).
                </p>

                {{-- Tabs tipo "segmented control": sin componente x-ui.tabs
                     dedicado porque es un único toggle de 2 opciones usado
                     en una sola página — extraerlo sería una abstracción
                     prematura. Usa tokens existentes (--radius-control,
                     --color-surface, --shadow-sm), no colores inventados. --}}
                <div class="mb-5 inline-flex gap-1 rounded-[var(--radius-control)] bg-white/5 p-1" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        x-on:click="importTab = 'complete'"
                        x-bind:aria-selected="importTab === 'complete' ? 'true' : 'false'"
                        x-bind:class="importTab === 'complete' ? 'bg-[color:var(--color-surface)] text-[color:var(--color-text-primary)] shadow-[var(--shadow-sm)]' : 'text-[color:var(--color-text-secondary)] hover:text-[color:var(--color-text-primary)]'"
                        class="rounded-[calc(var(--radius-control)-2px)] px-4 py-2 text-sm font-medium transition"
                    >Backup completo</button>
                    <button
                        type="button"
                        role="tab"
                        x-on:click="importTab = 'multipart'"
                        x-bind:aria-selected="importTab === 'multipart' ? 'true' : 'false'"
                        x-bind:class="importTab === 'multipart' ? 'bg-[color:var(--color-surface)] text-[color:var(--color-text-primary)] shadow-[var(--shadow-sm)]' : 'text-[color:var(--color-text-secondary)] hover:text-[color:var(--color-text-primary)]'"
                        class="rounded-[calc(var(--radius-control)-2px)] px-4 py-2 text-sm font-medium transition"
                    >Backup dividido</button>
                </div>

                <div x-show="importTab === 'complete'" role="tabpanel">
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
                </div>

                <div
                    x-show="importTab === 'multipart'"
                    x-cloak
                    role="tabpanel"
                    aria-live="polite"
                    x-data="rucBackupMultipartUploader({
                        store: @js(route('admin.ruc.backups.multipart.store')),
                        show: @js(route('admin.ruc.backups.multipart.store').'/:upload'),
                        uploadPart: @js(route('admin.ruc.backups.multipart.store').'/:upload/parts/:index'),
                        destroy: @js(route('admin.ruc.backups.multipart.store').'/:upload'),
                    })"
                >
                    {{-- Máquina de estados: exactamente UN <template x-if> es
                         verdadero a la vez (no x-show — a diferencia de
                         x-show, x-if realmente quita del DOM lo que no
                         corresponde, así un bug en otro estado nunca puede
                         dejar dos bloques visibles al mismo tiempo). --}}

                    <template x-if="stage === 'idle'">
                        <div class="space-y-3">
                            <p class="text-sm text-[color:var(--color-text-secondary)]">Selecciona el archivo manifest generado por RUC Tools.</p>
                            <x-ui.file-dropzone
                                name="manifest"
                                label="Manifest del backup"
                                accept=".json"
                                help="Archivo *.manifest.json generado por ruc-tool backup"
                                on-select="onManifestSelected($event)"
                            />
                            <x-ui.alert x-show="manifestError" tone="danger">
                                <span x-text="manifestError"></span>
                            </x-ui.alert>
                        </div>
                    </template>

                    <template x-if="stage === 'manifest_selected'">
                        <div class="space-y-4">
                            <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] p-4">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-secondary)]">Backup detectado</p>
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
                                    <div class="col-span-2 sm:col-span-4">
                                        <dt class="text-xs text-[color:var(--color-text-secondary)]">Nombre</dt>
                                        <dd class="truncate font-mono text-xs" x-text="manifest?.original_filename"></dd>
                                    </div>
                                    <div><dt class="text-xs text-[color:var(--color-text-secondary)]">Registros</dt><dd class="font-medium" x-text="manifest ? new Intl.NumberFormat('es-PE').format(manifest.total_records) : ''"></dd></div>
                                    <div><dt class="text-xs text-[color:var(--color-text-secondary)]">Tamaño</dt><dd class="font-medium" x-text="formatBytes(totalSizeBytes)"></dd></div>
                                    <div><dt class="text-xs text-[color:var(--color-text-secondary)]">Partes</dt><dd class="font-medium" x-text="totalParts"></dd></div>
                                    <div><dt class="text-xs text-[color:var(--color-text-secondary)]">Tamaño máximo</dt><dd class="font-medium" x-text="manifest ? formatBytes(manifest.part_size_bytes) : ''"></dd></div>
                                    <div class="col-span-2 sm:col-span-4">
                                        <dt class="text-xs text-[color:var(--color-text-secondary)]">Checksum</dt>
                                        <dd class="truncate font-mono text-xs" x-text="manifest?.sha256"></dd>
                                    </div>
                                </dl>
                            </div>

                            <div>
                                <x-ui.file-dropzone
                                    name="parts"
                                    label="Partes del backup"
                                    multiple
                                    help="Selecciona todas las partes juntas (.part0001, .part0002...); se ordenan automáticamente."
                                    on-select="onPartsSelected($event)"
                                />
                                <x-ui.alert x-show="partsError" tone="warning" class="mt-2">
                                    <span x-text="partsError"></span>
                                </x-ui.alert>
                            </div>
                        </div>
                    </template>

                    <template x-if="stage === 'ready'">
                        <div class="space-y-4">
                            <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] p-4 text-sm">
                                <p class="font-medium" x-text="selectedPartsSummary"></p>
                                <p class="text-[color:var(--color-text-secondary)]"><span x-text="selectedPartsSizeLabel"></span> seleccionados</p>
                            </div>
                            <div class="flex justify-end">
                                <x-ui.button type="button" variant="primary" x-on:click="startUpload()">
                                    <x-ui.icon name="upload" class="size-4" /> Importar Backup
                                </x-ui.button>
                            </div>
                        </div>
                    </template>

                    <template x-if="stage === 'uploading'">
                        <div class="space-y-3">
                            <x-ui.operation-status title="Importando backup" status="running">
                                <x-slot:progress>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-2 text-sm">
                                            <span class="truncate font-medium" x-text="manifest?.original_filename"></span>
                                            <span class="shrink-0 text-[color:var(--color-text-secondary)]" x-text="overallProgressPercent + '%'"></span>
                                        </div>
                                        <div role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="overallProgressPercent" class="h-2 overflow-hidden rounded-full bg-white/10">
                                            <div class="h-full rounded-full bg-[color:var(--color-brand)] transition-[width] duration-300" x-bind:style="'width: ' + overallProgressPercent + '%'"></div>
                                        </div>
                                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-[color:var(--color-text-secondary)]">
                                            <span>Parte <span x-text="currentPartIndex"></span> de <span x-text="totalParts"></span> — <span x-text="formattedProgress"></span></span>
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-ui.icon name="clock" class="size-3.5" />
                                                <span x-text="elapsedLabel"></span>
                                                <template x-if="speedLabel"><span>· <span x-text="speedLabel"></span></span></template>
                                                <template x-if="etaLabel"><span>· <span x-text="etaLabel"></span></span></template>
                                            </span>
                                        </div>
                                    </div>
                                </x-slot:progress>
                            </x-ui.operation-status>
                            <div class="flex justify-end">
                                <x-ui.button type="button" variant="secondary" size="sm" x-on:click="cancel()" x-bind:disabled="cancelling">Cancelar</x-ui.button>
                            </div>
                        </div>
                    </template>

                    {{-- El ensamblado y la validación (checksum final,
                         pg_restore --list, registro del RucBackup) ocurren
                         en el servidor dentro de UNA sola respuesta HTTP (la
                         de la última parte) — no hay forma honesta de
                         reportar sub-progreso granular ahí sin inventar
                         temporización falsa. Se muestra progreso
                         indeterminado + lo único que el cliente sabe con
                         certeza en este punto: todas las partes ya fueron
                         verificadas individualmente. --}}
                    <template x-if="stage === 'assembling'">
                        <div>
                            <x-ui.operation-status
                                title="Ensamblando backup"
                                status="running"
                                message="Todas las partes fueron recibidas correctamente."
                            >
                                <x-slot:progress>
                                    <x-ui.progress indeterminate label="Uniendo partes…" :show-value="false" />
                                </x-slot:progress>
                                <x-slot:steps>
                                    <x-ui.process-steps class="mt-1" :steps="[
                                        ['label' => 'Partes verificadas y subidas', 'status' => 'completed'],
                                        ['label' => 'Ensamblando y validando en el servidor', 'status' => 'active'],
                                        ['label' => 'Registrando backup', 'status' => 'pending'],
                                    ]" />
                                </x-slot:steps>
                            </x-ui.operation-status>
                        </div>
                    </template>

                    <template x-if="stage === 'completed'">
                        <x-ui.alert tone="success" title="Backup importado correctamente">
                            <p class="truncate font-mono text-xs" x-text="manifest?.original_filename"></p>
                            <p class="mt-0.5 text-xs text-[color:var(--color-text-secondary)]">
                                <span x-text="formatBytes(totalSizeBytes)"></span> · <span x-text="manifest ? new Intl.NumberFormat('es-PE').format(manifest.total_records) : ''"></span> registros
                            </p>
                            <x-slot:actions>
                                <x-ui.button type="button" size="sm" variant="secondary" x-on:click="window.location.reload()">Ver en la lista</x-ui.button>
                                <x-ui.button type="button" size="sm" variant="ghost" x-on:click="reset()">Importar otro</x-ui.button>
                            </x-slot:actions>
                        </x-ui.alert>
                    </template>

                    <template x-if="stage === 'failed'">
                        <x-ui.alert tone="danger" title="Error al importar el backup">
                            <span x-text="errorMessage"></span>
                            <x-slot:actions>
                                <x-ui.button type="button" size="sm" variant="secondary" x-on:click="retry()">Reintentar</x-ui.button>
                                <x-ui.button type="button" size="sm" variant="ghost" x-on:click="reset()">Empezar de nuevo</x-ui.button>
                            </x-slot:actions>
                        </x-ui.alert>
                    </template>

                    <template x-if="stage === 'cancelled'">
                        <x-ui.alert tone="neutral" title="Importación cancelada">
                            Las partes temporales fueron eliminadas.
                            <x-slot:actions>
                                <x-ui.button type="button" size="sm" variant="secondary" x-on:click="reset()">Empezar de nuevo</x-ui.button>
                            </x-slot:actions>
                        </x-ui.alert>
                    </template>
                </div>
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
