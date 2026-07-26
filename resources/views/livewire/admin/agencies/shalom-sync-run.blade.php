<div wire:poll.5s>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Sincronización Shalom #{{ $importRun->id }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $importRun->stage ?: 'Pendiente' }} · {{ $importRun->progress }}%</p>
        </div>
        <div class="flex gap-2">
            @if($importRun->extracted_json_path)
                <a class="btn-secondary" href="{{ route('admin.agencies.import.run.download', [$importRun, 'processed']) }}">Descargar JSON</a>
            @endif
            @if($importRun->report_json_path)
                <a class="btn-secondary" href="{{ route('admin.agencies.import.run.download', [$importRun, 'report']) }}">Descargar reporte</a>
            @endif
        </div>
    </div>

    <div class="mb-6 h-3 overflow-hidden rounded-full bg-[color:var(--color-border-subtle)]">
        <div class="h-full bg-blue-600 transition-all" style="width: {{ max(0, min(100, $importRun->progress)) }}%"></div>
    </div>

    @if($importRun->error_message)
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <p>{{ $importRun->error_message }}</p>
            @if(in_array($importRun->status, ['pending', 'failed'], true))
                <button wire:click="retry" class="btn-secondary mt-3">Reintentar análisis</button>
            @endif
        </div>
    @elseif($importRun->status === 'pending')
        <div class="mb-6 rounded-lg border border-amber-400/40 bg-amber-500/10 p-4 text-sm text-amber-100">
            La ejecución sigue en cola. Si permanece así más de un minuto, reinicia el contenedor <code>codered-queue</code> y confirma que escucha <code>agency-imports</code>.
            <button wire:click="retry" class="btn-secondary ml-3">Enviar nuevamente</button>
        </div>
    @endif

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
        @foreach(['create' => 'Nuevas', 'update' => 'Actualizadas', 'rename' => 'Renombradas', 'unchanged' => 'Sin cambios', 'conflict' => 'Conflictos', 'missing' => 'No encontradas', 'invalid' => 'Inválidas'] as $key => $label)
            <button wire:click="$set('action', '{{ $key }}')" class="rounded-lg border p-3 text-left {{ $action === $key ? 'border-[color:var(--color-brand)] ring-2 ring-[color:var(--color-focus-ring)]' : 'border-[color:var(--color-border)]' }}">
                <span class="block text-xs text-slate-500">{{ $label }}</span>
                <strong class="text-xl">{{ $counts[$key] ?? 0 }}</strong>
            </button>
        @endforeach
    </div>

    @if($action !== '')
        <button wire:click="$set('action', '')" class="mb-4 text-sm text-blue-600">Quitar filtro</button>
    @endif

    @if($importRun->status === 'ready_for_review')
        <div class="mb-4 flex flex-wrap gap-2">
            <button wire:click="selectAction('create', true)" class="btn-secondary">Seleccionar nuevas</button>
            <button wire:click="selectAction('update', true)" class="btn-secondary">Seleccionar actualizadas</button>
            <button wire:click="selectAction('rename', true)" class="btn-secondary">Seleccionar renombradas</button>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-[color:var(--color-border)]">
        <table class="min-w-full divide-y divide-[color:var(--color-border-subtle)] text-sm">
            <thead class="bg-[color:var(--color-background-elevated)]">
                <tr><th class="p-3">Aplicar</th><th class="p-3">Acción</th><th class="p-3">ID externo</th><th class="p-3">Agencia</th><th class="p-3">Cambios</th><th class="p-3">Conflicto</th></tr>
            </thead>
            <tbody class="divide-y divide-[color:var(--color-border-subtle)]">
                @forelse($items as $item)
                    <tr>
                        <td class="p-3 text-center">
                            <input type="checkbox" @checked($item->selected) @disabled($importRun->status !== 'ready_for_review' || in_array($item->action, ['conflict','invalid','unchanged','missing'], true)) wire:click="toggleItem({{ $item->id }})">
                        </td>
                        <td class="p-3 font-medium">{{ $item->action }}</td>
                        <td class="p-3">{{ $item->external_id ?: '—' }}</td>
                        <td class="p-3">
                            <div>{{ data_get($item->incoming_data, 'name', '—') }}</div>
                            @if($item->action === 'rename')
                                <div class="mt-1 text-xs text-amber-700">Actual: {{ data_get($item->current_data, 'name') }}</div>
                                <div class="text-xs text-slate-500">old_name propuesto: {{ $item->proposed_old_name }}</div>
                            @endif
                        </td>
                        <td class="p-3"><pre class="max-w-xl whitespace-pre-wrap text-xs">{{ json_encode($item->differences, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                        <td class="p-3 text-red-700">{{ $item->conflict_reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">Aún no hay elementos para mostrar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>

    @if($importRun->status === 'ready_for_review')
        <div class="mt-6 flex justify-end">
            <button wire:click="confirm" wire:confirm="¿Confirmas la importación de los registros seleccionados?" class="btn-primary">Confirmar importación</button>
        </div>
    @elseif($importRun->status === 'completed')
        <div class="mt-6 rounded-lg border border-green-300 bg-green-50 p-4 text-green-800">Importación completada correctamente.</div>
    @endif
</div>
