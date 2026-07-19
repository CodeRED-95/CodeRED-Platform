<div class="space-y-6">
    <x-ui.page-header title="Importar agencias" subtitle="JSON local o URL raw de GitHub Gist.">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.agencies.index') }}" variant="secondary">Volver</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Origen y configuración" subtitle="Selecciona la fuente y revisa la estrategia antes de continuar." />
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
                    <x-ui.textarea wire:model.defer="jsonPayload" rows="10" label="Contenido JSON" :error="$errors->first('jsonPayload')" />
                @elseif($sourceType === 'url')
                    <x-ui.input type="url" wire:model.defer="url" label="URL raw" :error="$errors->first('url')" />
                @else
                    <x-ui.input type="file" wire:model="file" accept="application/json" label="Archivo JSON" :error="$errors->first('file')" />
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
                    <x-ui.button type="button" wire:click="preview" variant="secondary" loading-target="preview" loading-label="Validando…" wire:loading.attr="disabled" wire:target="preview">Vista previa</x-ui.button>
                    <x-ui.button type="button" wire:click="import" variant="primary" loading-target="import" loading-label="Importando…" wire:loading.attr="disabled" wire:target="import">Importar</x-ui.button>
                </div>
                @if($message)
                    <x-ui.alert tone="success">{{ $message }}</x-ui.alert>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="Resumen" subtitle="Resultado de la validación previa a la importación." />
            <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <x-ui.stat-card label="Total" :value="$summary['total_rows'] ?? 0" tone="brand" />
                <x-ui.stat-card label="Válidos" :value="$summary['valid_rows'] ?? 0" tone="success" />
                <x-ui.stat-card label="Advertencias" :value="$summary['warning_rows'] ?? 0" tone="warning" />
                <x-ui.stat-card label="Inválidos" :value="$summary['invalid_rows'] ?? 0" tone="danger" />
            </div>
            <div class="mt-6 space-y-3">
                @forelse($preview as $item)
                    <article class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/5 p-4">
                        <div class="font-medium">{{ $item['code'] ?? 'SIN CÓDIGO' }} · {{ $item['name'] ?? 'Sin nombre' }}</div>
                        <div class="text-sm text-[color:var(--color-text-secondary)]">{{ $item['department'] ?? '—' }} / {{ $item['province'] ?? '—' }} / {{ $item['district'] ?? '—' }}</div>
                        <div class="text-sm text-[color:var(--color-text-secondary)]">{{ $item['latitude'] ?? '—' }}, {{ $item['longitude'] ?? '—' }} · {{ $item['size'] ?? '—' }} · CO: {{ !empty($item['is_operations_center']) ? 'Sí' : 'No' }}</div>
                        @if(!empty($item['warnings']))
                            <ul class="mt-2 list-disc pl-5 text-xs text-amber-600">
                                @foreach($item['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @empty
                    <x-ui.empty-state title="Sin vista previa" description="Genera una vista previa para ver los primeros 20 registros." icon="⇪" />
                @endforelse
            </div>
        </x-ui.card>
    </div>
</div>
