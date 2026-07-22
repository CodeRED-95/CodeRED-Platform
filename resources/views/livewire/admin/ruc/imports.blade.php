<div class="space-y-6">
    <x-ui.page-header eyebrow="Empresas y RUC" title="Importaciones RUC" subtitle="Padrón reducido SUNAT procesado por streaming, PostgreSQL COPY y cola exclusiva.">
        <x-slot:actions><x-ui.button wire:click="scanFiles" loading-target="scanFiles">Detectar archivos</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" title="Directorio de entrada">Coloca el TXT manualmente en <code>storage/app/private/ruc/incoming</code>. Los archivos de varios GB no se transfieren por Livewire.</x-ui.alert>

    <x-ui.card title="Diagnóstico del almacenamiento">
        <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-[color:var(--color-text-muted)]">Disk</dt><dd>{{ $diagnostics['disk'] ?? '—' }}</dd></div>
            <div><dt class="text-[color:var(--color-text-muted)]">Directorio</dt><dd>{{ $diagnostics['configured_directory'] ?? '—' }}</dd></div>
            <div><dt class="text-[color:var(--color-text-muted)]">Existe / legible</dt><dd>{{ ($diagnostics['exists'] ?? false) ? 'Sí' : 'No' }} / {{ ($diagnostics['readable'] ?? false) ? 'Sí' : 'No' }}</dd></div>
            <div><dt class="text-[color:var(--color-text-muted)]">TXT detectados</dt><dd>{{ $diagnostics['txt_count'] ?? 0 }}</dd></div>
            <div class="md:col-span-2 xl:col-span-4"><dt class="text-[color:var(--color-text-muted)]">Ruta física dentro de Docker</dt><dd class="break-all font-mono">{{ $diagnostics['physical_path'] ?? '—' }}</dd></div>
        </dl>
    </x-ui.card>

    @if($validationMessage)<x-ui.alert tone="success">{{ $validationMessage }}</x-ui.alert>@endif

    <x-ui.card title="Archivos TXT disponibles">
        <x-ui.table><thead><tr><th>Nombre</th><th>Tamaño</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
        @forelse($availableFiles as $file)
            <tr wire:key="ruc-file-{{ md5($file['path']) }}">
                <td>{{ $file['name'] }}</td><td>{{ number_format($file['size'] / 1048576, 2) }} MB</td>
                <td>{{ \Carbon\Carbon::createFromTimestamp($file['last_modified'])->format('d/m/Y H:i:s') }}</td>
                <td><x-ui.badge>{{ str_replace('_', ' ', $file['status']) }}</x-ui.badge></td>
                <td><div class="flex flex-wrap gap-2">
                    <x-ui.button size="sm" variant="secondary" wire:click="validateFile(@js($file['path']))">Validar</x-ui.button>
                    @if($file['status'] === 'no_registrado')
                        <x-ui.button size="sm" wire:click="registerFile(@js($file['path']))">Registrar</x-ui.button>
                    @elseif($file['status'] === 'registrado' && $file['import_id'])
                        <x-ui.button size="sm" variant="success" wire:click="startImport({{ $file['import_id'] }})">Iniciar importación</x-ui.button>
                    @endif
                </div></td>
            </tr>
        @empty<tr><td colspan="5"><x-ui.empty-state title="No hay TXT disponibles" description="Copia el padrón SUNAT al directorio incoming y pulsa Detectar archivos." /></td></tr>@endforelse
        </tbody></x-ui.table>
    </x-ui.card>

    @if($activeImport)
        <x-ui.card wire:poll.2s title="Importación activa" description="{{ $activeImport->original_filename }}">
            <div class="mb-3 flex items-center justify-between"><x-ui.badge :tone="$activeImport->status->tone()">{{ $activeImport->status->label() }}</x-ui.badge><strong>{{ $activeImport->progress_percentage }}%</strong></div>
            <div class="h-3 overflow-hidden rounded-full bg-white/10"><div class="h-full bg-[color:var(--color-brand)] transition-all" style="width: {{ min(100, (float) $activeImport->progress_percentage) }}%"></div></div>
            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div>Procesadas: <strong>{{ number_format($activeImport->processed_rows) }} / {{ $activeImport->total_rows ? number_format($activeImport->total_rows) : 'calculando' }}</strong></div>
                <div>Nuevos: <strong>{{ number_format($activeImport->inserted_rows) }}</strong></div>
                <div>Existentes: <strong>{{ number_format($activeImport->ignored_rows) }}</strong></div>
                <div>Inválidos: <strong>{{ number_format($activeImport->invalid_rows) }}</strong></div>
                <div>Direcciones construidas: <strong>{{ number_format($activeImport->address_rows) }}</strong></div>
                <div>Ubigeos resueltos: <strong>{{ number_format($activeImport->resolved_ubigeo_rows) }}</strong></div>
                <div>Ubigeos desconocidos: <strong>{{ number_format($activeImport->unknown_ubigeo_rows) }}</strong></div>
                <div>Velocidad: <strong>{{ number_format($speed, 1) }} filas/s</strong></div>
                <div>ETA: <strong>{{ $remainingSeconds === null ? 'Calculando' : gmdate('H:i:s', $remainingSeconds) }}</strong></div>
                <div>Heartbeat: <strong>{{ $activeImport->last_heartbeat_at?->diffForHumans() ?? 'Sin actividad' }}</strong></div>
                <div class="sm:col-span-2">Mensaje: <strong>{{ $activeImport->last_message }}</strong></div>
            </dl>
            <div class="mt-4 flex gap-2">
                @if($activeImport->status === \App\Modules\Ruc\Enums\RucImportStatus::Processing)<x-ui.button variant="secondary" wire:click="pause({{ $activeImport->id }})">Pausar</x-ui.button>@endif
                @if(in_array($activeImport->status, [\App\Modules\Ruc\Enums\RucImportStatus::Paused, \App\Modules\Ruc\Enums\RucImportStatus::Failed], true))<x-ui.button wire:click="resume({{ $activeImport->id }})">Reanudar</x-ui.button>@endif
                <x-ui.button variant="danger" wire:click="cancel({{ $activeImport->id }})">Cancelar</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card title="Historial"><x-ui.table><thead><tr><th>Archivo</th><th>Estado</th><th>Progreso</th><th>Nuevos</th><th>Existentes</th><th>Inválidos</th><th>Ubigeos</th></tr></thead><tbody>
        @forelse($imports as $import)<tr><td>{{ $import->original_filename }}</td><td><x-ui.badge :tone="$import->status->tone()">{{ $import->status->label() }}</x-ui.badge></td><td>{{ $import->progress_percentage }}%</td><td>{{ number_format($import->inserted_rows) }}</td><td>{{ number_format($import->ignored_rows) }}</td><td>{{ number_format($import->invalid_rows) }}</td><td>{{ number_format($import->resolved_ubigeo_rows) }} / {{ number_format($import->unknown_ubigeo_rows) }}</td></tr>
        @empty<tr><td colspan="7"><x-ui.empty-state title="No hay importaciones RUC" /></td></tr>@endforelse
    </tbody></x-ui.table><x-ui.pagination :paginator="$imports" /></x-ui.card>
</div>
