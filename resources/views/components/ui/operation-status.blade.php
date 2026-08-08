@props([
    'title',
    'status' => 'running',
    'message' => null,
    'elapsed' => null,
])

{{--
    Composición ligera (no monolítica) para representar una operación larga:
    título + badge de estado + tiempo transcurrido, con slots opcionales
    "progress" y "steps" para insertar x-ui.progress / x-ui.process-steps.
    Ejemplo:
        <x-ui.operation-status title="Restaurando backup" status="running" elapsed="02:14">
            <x-slot:progress><x-ui.progress indeterminate label="Restaurando datos de ruc_records" /></x-slot:progress>
        </x-ui.operation-status>
--}}
@php
    $badgeTone = ['running' => 'info', 'completed' => 'success', 'failed' => 'danger'][$status] ?? 'neutral';
    $badgeLabel = ['running' => 'En progreso', 'completed' => 'Completado', 'failed' => 'Falló'][$status] ?? ucfirst($status);
@endphp

<div {{ $attributes->class('space-y-4') }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-base font-semibold">{{ $title }}</h3>
            @if ($message)
                <p class="mt-0.5 text-sm text-[color:var(--color-text-secondary)]">{{ $message }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if ($elapsed)
                <span class="inline-flex items-center gap-1.5 text-xs text-[color:var(--color-text-secondary)]" role="timer">
                    <x-ui.icon name="clock" class="size-3.5" />
                    {{ $elapsed }}
                </span>
            @endif
            <x-ui.badge :tone="$badgeTone">{{ $badgeLabel }}</x-ui.badge>
        </div>
    </div>

    @isset($progress)
        {{ $progress }}
    @endisset

    @isset($steps)
        {{ $steps }}
    @endisset

    {{ $slot }}
</div>
