<div class="space-y-6">
    <x-ui.page-header title="Importar agencias" subtitle="Asistente seguro para GitHub Gist, JSON o archivo local.">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.agencies.index') }}" variant="secondary">Volver</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <nav aria-label="Progreso de importación">
        <ol class="grid gap-2 sm:grid-cols-5">
            @foreach ([1 => 'Origen', 2 => 'Validar', 3 => 'Vista previa', 4 => 'Importar', 5 => 'Resumen'] as $number => $label)
                <li @class([
                    'flex items-center gap-3 rounded-[var(--radius-control)] border px-3 py-3 text-sm transition',
                    'border-[color:var(--color-brand)] bg-[color:var(--color-brand-soft)] text-white' => $step === $number,
                    'border-emerald-500/30 bg-emerald-500/5 text-emerald-300' => $step > $number,
                    'border-[color:var(--color-border-subtle)] text-[color:var(--color-text-muted)]' => $step < $number,
                ]) @if ($step === $number) aria-current="step" @endif>
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full border border-current text-xs font-semibold">
                        {{ $step > $number ? '✓' : $number }}
                    </span>
                    <span>{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($errors->has('source') || $errors->has('import'))
        <x-ui.alert tone="danger">{{ $errors->first('source') ?: $errors->first('import') }}</x-ui.alert>
    @endif

    @if ($step === 1)
        <x-ui.card class="mx-auto max-w-3xl">
            <x-ui.section-header title="1. Selecciona el origen" description="La URL debe apuntar al contenido raw de un Gist o repositorio permitido." />
            <div class="mt-6 grid gap-5">
                <x-ui.dropdown-select id="import-source" name="sourceType" wire:model.live="sourceType" label="Fuente" :value="$sourceType" :options="['url' => 'URL de GitHub Gist', 'json' => 'JSON pegado', 'file' => 'Archivo JSON']" />

                @if ($sourceType === 'url')
                    <x-ui.input type="url" wire:model.defer="url" label="URL raw del Gist" placeholder="https://gist.githubusercontent.com/.../raw/..." :error="$errors->first('url')" />
                    <p class="text-sm text-[color:var(--color-text-muted)]">Solo se aceptan HTTPS, gist.githubusercontent.com y raw.githubusercontent.com.</p>
                @elseif ($sourceType === 'json')
                    <x-ui.textarea wire:model.defer="jsonPayload" rows="12" label="Contenido JSON" :error="$errors->first('jsonPayload')" />
                @else
                    <x-ui.input type="file" wire:model="file" accept="application/json,.json" label="Archivo JSON" :error="$errors->first('file')" />
                @endif

                <div class="flex justify-end">
                    <x-ui.button type="button" wire:click="goToValidation" variant="primary">Continuar</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @elseif ($step === 2)
        <x-ui.card class="mx-auto max-w-3xl">
            <x-ui.section-header title="2. Validar fuente" description="Se verificará todo el archivo, sus campos y posibles duplicados. Aún no se importará nada." />
            <div class="mt-6 rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.03] p-4 text-sm">
                <p class="font-medium">Origen preparado</p>
                <p class="mt-1 break-all text-[color:var(--color-text-secondary)]">
                    {{ $sourceType === 'url' ? $url : ($sourceType === 'file' ? $file?->getClientOriginalName() : 'JSON pegado manualmente') }}
                </p>
            </div>
            <div class="mt-6 flex flex-wrap justify-between gap-3">
                <x-ui.button type="button" wire:click="backToSource" variant="secondary">Cambiar origen</x-ui.button>
                <x-ui.button type="button" wire:click="validateAndPreview" variant="primary" loading-target="validateAndPreview" loading-label="Validando todo el archivo…" wire:loading.attr="disabled" wire:target="validateAndPreview">Validar y generar vista previa</x-ui.button>
            </div>
        </x-ui.card>
    @elseif ($step === 3)
        <div class="grid gap-6 xl:grid-cols-[0.75fr_1.25fr]">
            <div class="space-y-6">
                <x-ui.card>
                    <x-ui.section-header title="3. Resultado de validación" description="Estadísticas calculadas sobre todas las filas." />
                    @if ($payloadMetadata !== [])
                        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div><span class="text-[color:var(--color-text-muted)]">Formato</span><p>{{ $payloadMetadata['format'] ?? 'JSON' }}</p></div>
                            <div><span class="text-[color:var(--color-text-muted)]">Versión</span><p>{{ $payloadMetadata['schema_version'] ?? 'Legado' }}</p></div>
                            <div><span class="text-[color:var(--color-text-muted)]">Total declarado</span><p>{{ $payloadMetadata['declared_count'] ?? 'No indicado' }}</p></div>
                        </div>
                    @endif
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <x-ui.stat-card label="Total" :value="$summary['total_rows'] ?? 0" tone="brand" />
                        <x-ui.stat-card label="Válidas" :value="$summary['valid_rows'] ?? 0" tone="success" />
                        <x-ui.stat-card label="Con advertencias" :value="$summary['warning_rows'] ?? 0" tone="warning" />
                        <x-ui.stat-card label="Inválidas" :value="$summary['invalid_rows'] ?? 0" tone="danger" />
                        <x-ui.stat-card label="Duplicadas" :value="$summary['duplicate_rows'] ?? 0" tone="info" />
                        <x-ui.stat-card label="Heredados clasificados" :value="$summary['legacy_classified'] ?? 0" tone="success" />
                        <x-ui.stat-card label="Heredados sin clasificar" :value="$summary['legacy_unclassified'] ?? 0" tone="warning" />
                        <x-ui.stat-card label="Conflictos de identidad" :value="$summary['identity_conflicts'] ?? 0" tone="danger" />
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <x-ui.section-header title="Configuración" description="Define cómo tratar registros existentes y altas nuevas." />
                    <div class="mt-5 grid gap-4">
                        <x-ui.dropdown-select id="import-strategy" name="strategy" wire:model="strategy" label="Estrategia de duplicados" :value="$strategy" :options="['ignore_existing' => 'Ignorar existentes', 'update_existing' => 'Actualizar existentes', 'create_only_new' => 'Crear solo nuevos', 'mark_conflicts' => 'Marcar conflictos']" />
                        <x-ui.status-select id="import-initial-status" name="statusOnCreate" wire:model="statusOnCreate" label="Estado inicial de nuevas agencias" :value="$statusOnCreate" :options="['under_review' => 'En revisión', 'active' => 'Activa']" />
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card>
                <x-ui.section-header title="Vista previa" description="Se muestran hasta 20 filas; las estadísticas corresponden al archivo completo." />
                <div class="mt-5 max-h-[42rem] space-y-3 overflow-y-auto pr-1">
                    @foreach ($preview as $item)
                        <article class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.03] p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium">Fila {{ $item['row_number'] }} · {{ $item['code'] ?? 'SIN CÓDIGO' }} · {{ $item['name'] ?? 'Sin nombre' }}</p>
                                    <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">{{ $item['department'] ?? '—' }} / {{ $item['province'] ?? '—' }} / {{ $item['district'] ?? '—' }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <x-ui.badge :tone="($item['valid'] ?? false) ? 'success' : 'danger'">{{ ($item['valid'] ?? false) ? 'Válida' : 'Inválida' }}</x-ui.badge>
                                    @if ($item['duplicate'] ?? false)<x-ui.badge tone="info">Duplicada</x-ui.badge>@endif
                                    @if ($item['identity_conflict'] ?? false)<x-ui.badge tone="danger">Conflicto de identidad</x-ui.badge>@endif
                                </div>
                            </div>
                            @if (! empty($item['errors']))
                                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-rose-200">@foreach ($item['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                            @endif
                            @if (! empty($item['warnings']))
                                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-amber-200">@foreach ($item['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                            @endif
                        </article>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <div class="flex flex-wrap justify-between gap-3">
            <x-ui.button type="button" wire:click="backToSource" variant="secondary">Cambiar origen</x-ui.button>
            <x-ui.button type="button" wire:click="goToImport" variant="primary" :disabled="($summary['valid_rows'] ?? 0) === 0">Continuar a confirmación</x-ui.button>
        </div>
    @elseif ($step === 4)
        <x-ui.card class="mx-auto max-w-3xl">
            <x-ui.section-header title="4. Confirmar importación" description="Este es el último paso antes de escribir en la base de datos." />
            <dl class="mt-6 divide-y divide-white/5 rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] px-5">
                <div class="flex justify-between gap-4 py-4"><dt class="text-[color:var(--color-text-secondary)]">Filas totales</dt><dd class="font-semibold">{{ $summary['total_rows'] }}</dd></div>
                <div class="flex justify-between gap-4 py-4"><dt class="text-[color:var(--color-text-secondary)]">Duplicadas detectadas</dt><dd class="font-semibold">{{ $summary['duplicate_rows'] }}</dd></div>
                <div class="flex justify-between gap-4 py-4"><dt class="text-[color:var(--color-text-secondary)]">Estrategia</dt><dd class="font-semibold">{{ str_replace('_', ' ', $strategy) }}</dd></div>
                <div class="flex justify-between gap-4 py-4"><dt class="text-[color:var(--color-text-secondary)]">Estado inicial</dt><dd class="font-semibold">{{ $statusOnCreate === 'active' ? 'Activa' : 'En revisión' }}</dd></div>
            </dl>
            <x-ui.alert tone="warning" class="mt-5">La importación utilizará exactamente el snapshot validado. No volverá a descargar la URL.</x-ui.alert>
            <div class="mt-6 flex flex-wrap justify-between gap-3">
                <x-ui.button type="button" wire:click="backToPreview" variant="secondary">Volver a vista previa</x-ui.button>
                <x-ui.button type="button" wire:click="import" variant="primary" loading-target="import" loading-label="Importando snapshot…" wire:loading.attr="disabled" wire:target="import">Confirmar e importar</x-ui.button>
            </div>
        </x-ui.card>
    @else
        <div class="mx-auto max-w-4xl space-y-6">
            <x-ui.alert :tone="($summary['failed'] ?? 0) > 0 ? 'warning' : 'success'">{{ $message }}</x-ui.alert>
            <x-ui.card>
                <x-ui.section-header title="5. Resumen final" description="Resultado persistido de la importación #{{ $completedImportId }}." />
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.stat-card label="Importadas" :value="$summary['imported'] ?? 0" tone="success" />
                    <x-ui.stat-card label="Actualizadas" :value="$summary['updated'] ?? 0" tone="info" />
                    <x-ui.stat-card label="Restauradas" :value="$summary['restored'] ?? 0" tone="success" />
                    <x-ui.stat-card label="Omitidas" :value="$summary['skipped'] ?? 0" tone="warning" />
                    <x-ui.stat-card label="Fallidas" :value="$summary['failed'] ?? 0" tone="danger" />
                    <x-ui.stat-card label="Advertencias" :value="$summary['warnings'] ?? 0" tone="warning" />
                    <x-ui.stat-card label="Heredados clasificados" :value="$summary['legacy_classified'] ?? 0" tone="success" />
                    <x-ui.stat-card label="Heredados sin clasificar" :value="$summary['legacy_unclassified'] ?? 0" tone="warning" />
                    <x-ui.stat-card label="Conflictos de identidad" :value="$summary['identity_conflicts'] ?? 0" tone="danger" />
                </div>

                @if ($failures !== [])
                    <div class="mt-6">
                        <h3 class="font-semibold">Incidencias</h3>
                        <div class="mt-3 space-y-2">
                            @foreach ($failures as $failure)
                                <div class="rounded-[var(--radius-control)] border border-rose-500/20 bg-rose-500/5 p-3 text-sm">
                                    <span class="font-medium">Fila {{ $failure['row'] ?: 'conflicto' }}:</span>
                                    {{ implode(' ', $failure['errors'] ?? []) }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-ui.card>
            <div class="flex flex-wrap justify-center gap-3">
                <x-ui.button href="{{ route('admin.agencies.index') }}" variant="primary">Ver agencias</x-ui.button>
                <x-ui.button type="button" wire:click="resetWizard" variant="secondary">Nueva importación</x-ui.button>
            </div>
        </div>
    @endif
</div>
