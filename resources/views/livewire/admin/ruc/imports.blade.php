<div class="space-y-6" wire:poll.2s>
    <x-ui.page-header title="Importaciones RUC" subtitle="El worker procesa el TXT aunque cierres o actualices esta página."><x-slot:actions><x-ui.button href="{{ route('admin.ruc.records') }}" variant="secondary">Ver padrón</x-ui.button></x-slot:actions></x-ui.page-header>
    @if(auth()->user()->hasPermission('ruc.import'))
        <x-ui.card title="Subir padrón reducido SUNAT" description="TXT privado con configuración validada antes de encolarse.">
            <div class="mb-5 grid gap-3 text-sm sm:grid-cols-3">
                <div><span class="text-[color:var(--color-text-muted)]">Codificación</span><p class="font-medium">{{ $configuration['encoding'] }}</p></div>
                <div><span class="text-[color:var(--color-text-muted)]">Separador</span><p class="font-medium">{{ $configuration['delimiter'] }}</p></div>
                <div><span class="text-[color:var(--color-text-muted)]">Cola</span><p class="font-medium">{{ $configuration['queue'] }}</p></div>
            </div>
            @if (! $configuration['valid'])<x-ui.alert tone="danger" class="mb-5">{{ $configuration['message'] }}</x-ui.alert>@endif
            <div class="mb-5"><x-ui.button type="button" variant="secondary" size="sm" wire:click="checkConfiguration" loading-target="checkConfiguration">Comprobar configuración</x-ui.button></div>
            <form wire:submit="start" class="space-y-4">
                <x-ui.file-upload wire:model="file" accept=".txt,text/plain" label="Archivo TXT" description="Padrón reducido SUNAT · TXT separado por |" :error="$errors->first('file')" />
                @if($file)<p class="text-sm text-[color:var(--color-text-secondary)]">{{ $file->getClientOriginalName() }} · {{ number_format($file->getSize() / 1048576, 2) }} MB</p>@endif
                <x-ui.checkbox wire:model="force">Reprocesar aunque el hash ya exista; los RUC existentes seguirán sin sobrescribirse</x-ui.checkbox>
                <x-ui.button type="submit" loading-target="start">Iniciar importación en segundo plano</x-ui.button>
            </form>
        </x-ui.card>
    @endif
    @if($activeImport)
        <x-ui.card title="Importación activa" description="{{ $activeImport->original_filename }}">
            <div class="mb-3 flex items-center justify-between gap-3 text-sm">
                <x-ui.badge :tone="$activeImport->status->tone()">{{ $activeImport->status->label() }}</x-ui.badge>
                <span class="font-semibold tabular-nums">{{ $activeImport->progress_percentage }}%</span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-white/10"><div class="h-full bg-[color:var(--color-brand)] transition-all" style="width: {{ min(100, (float) $activeImport->progress_percentage) }}%"></div></div>
            <dl class="mt-5 grid gap-3 text-sm md:grid-cols-4">
                <div>Procesadas: <strong>{{ $activeImport->processed_rows }} / {{ $activeImport->total_rows ?: 'calculando' }}</strong></div>
                <div>Nuevas: <strong>{{ $activeImport->inserted_rows }}</strong></div>
                <div>Existentes: <strong>{{ $activeImport->ignored_rows }}</strong></div>
                <div>Inválidas: <strong>{{ $activeImport->invalid_rows }}</strong></div>
                <div>Velocidad: <strong>{{ $speed }} filas/s</strong></div>
                <div>ETA: <strong>{{ $remainingSeconds === null ? 'Calculando' : gmdate('H:i:s', $remainingSeconds) }}</strong></div>
                <div>Inicio: <strong>{{ $activeImport->started_at?->format('d/m/Y H:i:s') ?? 'Pendiente' }}</strong></div>
                <div>Cola: <strong>{{ $activeImport->queue_name }}</strong></div>
                <div class="md:col-span-2">Última actividad: <strong>{{ $activeImport->last_heartbeat_at?->diffForHumans() ?? 'No detectada' }}</strong></div>
                <div class="md:col-span-2">Mensaje: <strong>{{ $activeImport->last_message ?? 'Esperando información del worker.' }}</strong></div>
            </dl>
            @if($isStalled)
                <x-ui.alert tone="danger" class="mt-4">
                    El proceso no actualiza su actividad desde hace más de {{ max(1, (int) ceil(config('ruc.stalled_after_seconds') / 60)) }} minutos. Revisa el worker de colas antes de marcarlo como fallido.
                </x-ui.alert>
            @elseif($activeImport->status === \App\Modules\Ruc\Enums\RucImportStatus::Queued && $activeImport->last_heartbeat_at === null)
                <x-ui.alert tone="warning" class="mt-4">La importación está en cola, pero todavía no se detecta actividad del worker.</x-ui.alert>
            @endif
            @if($activeImport->error_message)<x-ui.alert tone="danger" class="mt-4">{{ $activeImport->error_message }}</x-ui.alert>@endif
            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button href="#import-ruc-{{ $activeImport->id }}" variant="secondary">Ver detalles</x-ui.button>
                @if($isStalled && auth()->user()->hasPermission('ruc.cancel-import'))
                    <x-ui.confirm-dialog id="fail-stalled-ruc-{{ $activeImport->id }}" title="Marcar importación como fallida" message="Hazlo solo después de confirmar que el worker ya no está procesando el archivo." confirm-label="Marcar como fallida" confirm-action="markStalledFailed({{ $activeImport->id }})"><x-slot:trigger><x-ui.button variant="danger">Marcar como fallida</x-ui.button></x-slot:trigger></x-ui.confirm-dialog>
                @endif
                @if(auth()->user()->hasPermission('ruc.cancel-import') && $activeImport->cancel_requested_at === null)
                    <x-ui.confirm-dialog id="cancel-ruc-{{ $activeImport->id }}" title="Cancelar importación" message="El worker se detendrá en el siguiente lote. Los registros ya confirmados se conservarán." confirm-label="Solicitar cancelación" confirm-action="cancel({{ $activeImport->id }})"><x-slot:trigger><x-ui.button variant="danger">Cancelar</x-ui.button></x-slot:trigger></x-ui.confirm-dialog>
                @endif
            </div>
        </x-ui.card>
    @endif
    <x-ui.card title="Historial"><x-ui.table><thead><tr><th class="px-4 py-3">Archivo</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Progreso</th><th class="px-4 py-3">Nuevos</th><th class="px-4 py-3">Ignorados</th><th class="px-4 py-3">Inválidos</th><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Acciones</th></tr></thead><tbody>
        @forelse($imports as $import)<tr id="import-ruc-{{ $import->id }}"><td class="px-4 py-3">{{ $import->original_filename }}<div class="font-mono text-xs text-[color:var(--color-text-muted)]">{{ $import->uuid }}</div><div class="mt-1 text-xs text-[color:var(--color-text-muted)]">{{ $import->last_message }}</div>@if($import->error_message)<div class="mt-1 text-xs text-[color:var(--color-danger)]">{{ $import->error_message }}</div>@endif</td><td class="px-4 py-3"><x-ui.badge :tone="$import->status->tone()">{{ $import->status->label() }}</x-ui.badge></td><td class="px-4 py-3">{{ $import->progress_percentage }}%</td><td class="px-4 py-3">{{ $import->inserted_rows }}</td><td class="px-4 py-3">{{ $import->ignored_rows }}</td><td class="px-4 py-3">{{ $import->invalid_rows }}</td><td class="px-4 py-3">{{ $import->createdBy?->name ?? 'Sistema' }}</td><td class="px-4 py-3"><div class="flex flex-wrap gap-2">@if($import->invalid_rows && auth()->user()->hasPermission('ruc.view-errors'))<x-ui.button href="{{ route('admin.ruc.imports.errors', $import) }}" size="sm" variant="secondary">Errores</x-ui.button>@endif @if(in_array($import->status, [\App\Modules\Ruc\Enums\RucImportStatus::Failed, \App\Modules\Ruc\Enums\RucImportStatus::Cancelled], true) && $import->path !== 'deleted' && auth()->user()->hasPermission('ruc.import'))<x-ui.confirm-dialog id="retry-ruc-{{ $import->id }}" title="Reintentar importación" message="Se procesará nuevamente el archivo sin sobrescribir RUC existentes." confirm-label="Reintentar" confirm-action="retry({{ $import->id }})"><x-slot:trigger><x-ui.button size="sm" variant="secondary">Reintentar</x-ui.button></x-slot:trigger></x-ui.confirm-dialog>@endif @if(!$import->status->active() && $import->path !== 'deleted' && auth()->user()->hasPermission('ruc.delete-import-file'))<x-ui.confirm-dialog id="delete-ruc-file-{{ $import->id }}" title="Eliminar archivo fuente" message="El historial y los RUC importados se conservarán." confirm-label="Eliminar archivo" confirm-action="deleteFile({{ $import->id }})"><x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar TXT</x-ui.button></x-slot:trigger></x-ui.confirm-dialog>@endif</div></td></tr>
        @empty<tr><td colspan="8" class="p-8 text-center">No hay importaciones.</td></tr>@endforelse
    </tbody></x-ui.table><x-ui.pagination :paginator="$imports" /></x-ui.card>
</div>
