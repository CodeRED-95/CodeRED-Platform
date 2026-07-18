<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold">Importar agencias</h2>
                <p class="text-sm text-slate-500">JSON local o URL raw de GitHub Gist.</p>
            </div>
            <a href="{{ route('admin.agencies.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm dark:border-slate-700">Volver</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-4">
                <x-ui.dropdown-select
                    id="import-source"
                    name="sourceType"
                    wire:model.live="sourceType"
                    label="Fuente"
                    :value="$sourceType"
                    :options="['json' => 'JSON pegado', 'url' => 'URL raw', 'file' => 'Archivo JSON']"
                />
                @if($sourceType === 'json')
                    <label>
                        <span class="text-sm font-medium">Contenido JSON</span>
                        <textarea wire:model.defer="jsonPayload" rows="10" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700"></textarea>
                    </label>
                @elseif($sourceType === 'url')
                    <label>
                        <span class="text-sm font-medium">URL raw</span>
                        <input type="url" wire:model.defer="url" class="mt-1 w-full rounded-xl border border-slate-200 bg-transparent px-4 py-3 text-sm dark:border-slate-700">
                    </label>
                @else
                    <label>
                        <span class="text-sm font-medium">Archivo JSON</span>
                        <input type="file" wire:model="file" accept="application/json" class="mt-1 w-full text-sm">
                    </label>
                @endif
                <x-ui.dropdown-select
                    id="import-strategy"
                    name="strategy"
                    wire:model="strategy"
                    label="Estrategia"
                    :value="$strategy"
                    :options="['ignore_existing' => 'Ignorar existentes', 'update_existing' => 'Actualizar existentes', 'create_only_new' => 'Crear solo nuevos', 'mark_conflicts' => 'Marcar conflictos']"
                />
                <x-ui.status-select
                    id="import-initial-status"
                    name="statusOnCreate"
                    wire:model="statusOnCreate"
                    label="Estado inicial"
                    :value="$statusOnCreate"
                    :options="['under_review' => 'En revisión', 'active' => 'Activa']"
                />
                <div class="flex gap-3">
                    <button type="button" wire:click="preview" class="rounded-xl border border-slate-200 px-4 py-2 text-sm dark:border-slate-700">Vista previa</button>
                    <button type="button" wire:click="import" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-slate-900">Importar</button>
                </div>
                @if($message)
                    <p class="text-sm text-emerald-600">{{ $message }}</p>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-semibold">Resumen</h3>
            <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">Total: {{ $summary['total_rows'] ?? 0 }}</div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">Válidos: {{ $summary['valid_rows'] ?? 0 }}</div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">Advertencias: {{ $summary['warning_rows'] ?? 0 }}</div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">Inválidos: {{ $summary['invalid_rows'] ?? 0 }}</div>
            </div>
            <div class="mt-6 space-y-3">
                @forelse($preview as $item)
                    <article class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                        <div class="font-medium">{{ $item['code'] ?? 'SIN CÓDIGO' }} · {{ $item['name'] ?? 'Sin nombre' }}</div>
                        <div class="text-sm text-slate-500">{{ $item['department'] ?? '—' }} / {{ $item['province'] ?? '—' }} / {{ $item['district'] ?? '—' }}</div>
                        <div class="text-sm text-slate-500">{{ $item['latitude'] ?? '—' }}, {{ $item['longitude'] ?? '—' }} · {{ $item['size'] ?? '—' }} · CO: {{ !empty($item['is_operations_center']) ? 'Sí' : 'No' }}</div>
                        @if(!empty($item['warnings']))
                            <ul class="mt-2 list-disc pl-5 text-xs text-amber-600">
                                @foreach($item['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Genera una vista previa para ver los primeros 20 registros.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
