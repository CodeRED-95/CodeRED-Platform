@props(['tone' => 'info', 'title' => null, 'dismissible' => false])

@php
    $tones = [
        'neutral' => 'border-white/10 bg-white/5 text-[color:var(--color-text-secondary)]',
        'info' => 'border-sky-500/20 bg-sky-500/10 text-sky-100',
        'success' => 'border-emerald-500/20 bg-emerald-500/10 text-emerald-100',
        'warning' => 'border-amber-500/20 bg-amber-500/10 text-amber-100',
        'danger' => 'border-rose-500/20 bg-rose-500/10 text-rose-100',
        'brand' => 'border-[color:var(--color-brand-soft)] bg-[color:var(--color-brand-soft)] text-white',
    ];
    $icons = [
        'neutral' => 'shield',
        'brand' => 'shield',
        'info' => 'info',
        'success' => 'success',
        'warning' => 'warning',
        'danger' => 'warning',
    ];

    // Si el caller pasa su propio x-show (p. ej. x-show="stage === 'done'"
    // en una máquina de estados), NUNCA escribirlo como un segundo atributo
    // x-show en el mismo <div>: HTML colapsa atributos duplicados al
    // primero que encuentra, así que ese x-show externo quedaría
    // silenciosamente ignorado y el alert se mostraría siempre (bug real,
    // visto en /admin/ruc/backups). En cambio, se combina en UNA sola
    // expresión con el "visible" interno (usado por el botón de descartar).
    $externalShow = $attributes->get('x-show');
    $showExpression = $externalShow ? "({$externalShow}) && visible" : 'visible';
@endphp

<div x-data="{ visible: true }" x-show="{{ $showExpression }}" role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->except('x-show')->merge(['class' => 'rounded-[var(--radius-card)] border p-4 '.($tones[$tone] ?? $tones['info'])]) }}>
    <div class="flex items-start gap-3">
        <span class="mt-0.5" aria-hidden="true"><x-ui.icon :name="$icons[$tone] ?? 'info'" class="size-4" /></span>
        <div class="min-w-0 flex-1">@if($title)<p class="font-semibold">{{ $title }}</p>@endif<div class="{{ $title ? 'mt-1 ' : '' }}text-sm leading-6">{{ $slot }}</div>@isset($actions)<div class="mt-3 flex flex-wrap gap-2">{{ $actions }}</div>@endisset</div>
        @if($dismissible)<button type="button" class="focus-ring rounded p-1 opacity-70 hover:bg-white/10 hover:opacity-100" x-on:click="visible = false" aria-label="Cerrar alerta">✕</button>@endif
    </div>
</div>
