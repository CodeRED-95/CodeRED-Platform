<div wire:poll.5s class="space-y-6">
    <x-ui.page-header
        title="Sincronización Shalom #{{ $importRun->id }}"
        subtitle="{{ $importRun->stage ?: 'Pendiente' }} · {{ $importRun->progress }}%"
    >
        <x-slot:actions>
            @if($importRun->extracted_json_path)
                <x-ui.button variant="secondary" href="{{ route('admin.agencies.import.run.download', [$importRun, 'processed']) }}">Descargar JSON</x-ui.button>
            @endif
            @if($importRun->report_json_path)
                <x-ui.button variant="secondary" href="{{ route('admin.agencies.import.run.download', [$importRun, 'report']) }}">Descargar reporte</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card variant="elevated">
        <x-ui.operation-status
            title="Ejecución de sincronización"
            :status="$importRun->status === 'completed' ? 'completed' : ($importRun->status === 'failed' ? 'failed' : 'running')"
            :message="'Procesando revisión de agencias para importación.'"
            :elapsed="$importRun->started_at ? $importRun->started_at->diffForHumans(now(), true) : null"
        >
            <x-slot:progress>
                <x-ui.progress :value="$importRun->progress" label="Progreso" tone="{{ $importRun->status === 'failed' ? 'danger' : 'info' }}" />
            </x-slot:progress>
        </x-ui.operation-status>
    </x-ui.card>

    @if($importRun->error_message)
        <x-ui.alert tone="danger" title="La sincronización presentó un error">
            <p>{{ $importRun->error_message }}</p>
            @if(in_array($importRun->status, ['pending', 'failed'], true))
                <x-slot:actions>
                    <x-ui.button type="button" variant="secondary" wire:click="retry" loading-target="retry">Reintentar análisis</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.alert>
    @elseif($importRun->status === 'pending')
        <x-ui.alert tone="warning" title="La ejecución sigue en cola">
            Si permanece así más de un minuto, reinicia el contenedor <code>codered-queue</code> y confirma que escucha <code>agency-imports</code>.
            <x-slot:actions>
                <x-ui.button type="button" variant="secondary" wire:click="retry" loading-target="retry">Enviar nuevamente</x-ui.button>
            </x-slot:actions>
        </x-ui.alert>
    @endif

    <x-ui.card title="Resumen de cambios" description="Filtra por tipo de cambio y aplica selecciones rápidas antes de confirmar la importación.">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
            @foreach(['create' => 'Nuevas', 'update' => 'Actualizadas', 'rename' => 'Renombradas', 'unchanged' => 'Sin cambios', 'conflict' => 'Conflictos', 'missing' => 'No encontradas', 'invalid' => 'Inválidas'] as $key => $label)
                <button wire:click="$set('action', '{{ $key }}')" class="rounded-[var(--radius-control)] border p-4 text-left transition {{ $action === $key ? 'border-[color:var(--color-brand)] bg-[color:var(--color-brand-soft)]' : 'border-[color:var(--color-border)] bg-[color:var(--color-surface)] hover:border-[color:var(--color-border)] hover:bg-[color:var(--color-surface-hover)]' }}">
                    <span class="block text-xs uppercase tracking-[0.2em] text-[color:var(--color-text-secondary)]">{{ $label }}</span>
                    <strong class="mt-2 block text-2xl">{{ $counts[$key] ?? 0 }}</strong>
                </button>
            @endforeach
        </div>

        @if($action !== '')
            <div class="mt-4">
                <x-ui.button type="button" variant="ghost" wire:click="$set('action', '')">Quitar filtro</x-ui.button>
            </div>
        @endif

        @if($importRun->status === 'ready_for_review')
            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="selectAction('create', true)">Seleccionar nuevas</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="selectAction('update', true)">Seleccionar actualizadas</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="selectAction('rename', true)">Seleccionar renombradas</x-ui.button>
            </div>
        @endif
    </x-ui.card>

    <x-ui.table stickyHeader>
        <thead>
            <tr>
                <th class="px-5 py-4">Aplicar</th>
                <th class="px-5 py-4">Acción</th>
                <th class="px-5 py-4">ID externo</th>
                <th class="px-5 py-4">Agencia</th>
                <th class="px-5 py-4">Cambios</th>
                <th class="px-5 py-4">Conflicto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr class="align-top">
                    <td class="px-5 py-4 text-center">
                        <input type="checkbox" @checked($item->selected) @disabled($importRun->status !== 'ready_for_review' || in_array($item->action, ['conflict','invalid','unchanged','missing'], true)) wire:click="toggleItem({{ $item->id }})" class="size-4 rounded border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-brand)] focus-ring">
                    </td>
                    <td class="px-5 py-4 font-medium">{{ $item->action }}</td>
                    <td class="px-5 py-4 font-mono text-sm">{{ $item->external_id ?: '—' }}</td>
                    <td class="px-5 py-4">
                        <div>{{ data_get($item->incoming_data, 'name', '—') }}</div>
                        @if($item->action === 'rename')
                            <div class="mt-1 text-xs text-[color:var(--color-text-secondary)]">Actual: {{ data_get($item->current_data, 'name') }}</div>
                            <div class="text-xs text-[color:var(--color-text-secondary)]">old_name propuesto: {{ $item->proposed_old_name }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <pre class="max-w-xl whitespace-pre-wrap rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)] p-3 text-xs text-[color:var(--color-text-secondary)]">{{ json_encode($item->differences, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                    </td>
                    <td class="px-5 py-4 text-[color:var(--color-danger)]">{{ $item->conflict_reason ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-12">
                        <x-ui.empty-state title="Aún no hay elementos para mostrar" description="La revisión aparecerá aquí cuando la extracción termine." />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <x-ui.pagination :paginator="$items" />

    @if($importRun->status === 'ready_for_review')
        <div class="flex justify-end">
            <x-ui.confirm-dialog id="confirm-import-run" title="Confirmar importación" message="¿Confirmas la importación de los registros seleccionados?" confirm-label="Confirmar importación" confirm-action="confirm">
                <x-slot:trigger><x-ui.button type="button" variant="primary">Confirmar importación</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>
        </div>
    @elseif($importRun->status === 'completed')
        <x-ui.alert tone="success" title="Importación completada correctamente" />
    @endif
</div>
