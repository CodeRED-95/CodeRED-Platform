<div class="space-y-6" wire:poll.2s>
    <x-ui.page-header title="Importaciones RUC" subtitle="El worker procesa el TXT aunque cierres o actualices esta página."><x-slot:actions><x-ui.button href="{{ route('admin.ruc.records') }}" variant="secondary">Ver padrón</x-ui.button></x-slot:actions></x-ui.page-header>
    @if(auth()->user()->hasPermission('ruc.import'))
        <x-ui.card title="Subir padrón reducido SUNAT" description="TXT privado; separador | y codificación latin-1 configurables.">
            <form wire:submit="start" class="space-y-4">
                <x-ui.input type="file" wire:model="file" accept=".txt,text/plain" label="Archivo TXT" :error="$errors->first('file')" />
                @if($file)<p class="text-sm text-[color:var(--color-text-secondary)]">{{ $file->getClientOriginalName() }} · {{ number_format($file->getSize() / 1048576, 2) }} MB</p>@endif
                <x-ui.checkbox wire:model="force">Reprocesar aunque el hash ya exista; los RUC existentes seguirán sin sobrescribirse</x-ui.checkbox>
                <x-ui.button type="submit" loading-target="start">Iniciar importación en segundo plano</x-ui.button>
            </form>
        </x-ui.card>
    @endif
    @if($activeImport)
        <x-ui.card title="Importación activa" description="{{ $activeImport->original_filename }}">
            <div class="mb-3 flex justify-between text-sm"><span>{{ $activeImport->status->value }}</span><span>{{ $activeImport->progress_percentage }}%</span></div>
            <div class="h-3 overflow-hidden rounded-full bg-white/10"><div class="h-full bg-[color:var(--color-brand)] transition-all" style="width: {{ min(100, (float) $activeImport->progress_percentage) }}%"></div></div>
            <dl class="mt-5 grid gap-3 md:grid-cols-4"><div>Procesadas: {{ $activeImport->processed_rows }} / {{ $activeImport->total_rows ?: 'calculando' }}</div><div>Nuevas: {{ $activeImport->inserted_rows }}</div><div>Existentes: {{ $activeImport->ignored_rows }}</div><div>Inválidas: {{ $activeImport->invalid_rows }}</div><div>Velocidad: {{ $speed }} filas/s</div><div>ETA: {{ $remainingSeconds === null ? 'Calculando' : gmdate('H:i:s', $remainingSeconds) }}</div><div>Heartbeat: {{ $activeImport->last_heartbeat_at?->diffForHumans() ?? 'Pendiente' }}</div><div>{{ number_format($activeImport->file_size / 1048576, 2) }} MB</div></dl>
            @if(auth()->user()->hasPermission('ruc.cancel-import'))<x-ui.button type="button" class="mt-4" variant="danger" wire:click="cancel({{ $activeImport->id }})">Cancelar</x-ui.button>@endif
        </x-ui.card>
    @endif
    <x-ui.card title="Historial"><x-ui.table><thead><tr><th class="px-4 py-3">Archivo</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Progreso</th><th class="px-4 py-3">Nuevos</th><th class="px-4 py-3">Ignorados</th><th class="px-4 py-3">Inválidos</th><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Acciones</th></tr></thead><tbody>
        @forelse($imports as $import)<tr><td class="px-4 py-3">{{ $import->original_filename }}<div class="font-mono text-xs text-[color:var(--color-text-muted)]">{{ $import->uuid }}</div></td><td class="px-4 py-3"><x-ui.badge :tone="str_starts_with($import->status->value, 'completed') ? 'success' : ($import->status->value === 'failed' ? 'danger' : 'info')">{{ $import->status->value }}</x-ui.badge></td><td class="px-4 py-3">{{ $import->progress_percentage }}%</td><td class="px-4 py-3">{{ $import->inserted_rows }}</td><td class="px-4 py-3">{{ $import->ignored_rows }}</td><td class="px-4 py-3">{{ $import->invalid_rows }}</td><td class="px-4 py-3">{{ $import->createdBy?->name ?? 'Sistema' }}</td><td class="px-4 py-3"><div class="flex gap-2">@if($import->invalid_rows && auth()->user()->hasPermission('ruc.view-errors'))<x-ui.button href="{{ route('admin.ruc.imports.errors', $import) }}" size="sm" variant="secondary">Errores</x-ui.button>@endif @if(!$import->status->active() && $import->path !== 'deleted' && auth()->user()->hasPermission('ruc.delete-import-file'))<x-ui.confirm-dialog id="delete-ruc-file-{{ $import->id }}" title="Eliminar archivo fuente" message="El historial y los RUC importados se conservarán." confirm-label="Eliminar archivo" confirm-action="deleteFile({{ $import->id }})"><x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar TXT</x-ui.button></x-slot:trigger></x-ui.confirm-dialog>@endif</div></td></tr>
        @empty<tr><td colspan="8" class="p-8 text-center">No hay importaciones.</td></tr>@endforelse
    </tbody></x-ui.table>{{ $imports->links() }}</x-ui.card>
</div>
