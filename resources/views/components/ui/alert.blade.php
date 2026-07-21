@props(['tone' => 'info', 'title' => null, 'dismissible' => false])

@php
    $tones = [
        'info' => 'border-sky-500/20 bg-sky-500/10 text-sky-100',
        'success' => 'border-emerald-500/20 bg-emerald-500/10 text-emerald-100',
        'warning' => 'border-amber-500/20 bg-amber-500/10 text-amber-100',
        'danger' => 'border-rose-500/20 bg-rose-500/10 text-rose-100',
        'brand' => 'border-[color:var(--color-brand-soft)] bg-[color:var(--color-brand-soft)] text-white',
    ];
@endphp

<div x-data="{ visible: true }" x-show="visible" role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] border p-4 '.($tones[$tone] ?? $tones['info'])]) }}>
    <div class="flex items-start gap-3">
        <span class="mt-0.5" aria-hidden="true">{{ $tone === 'success' ? '✓' : ($tone === 'danger' ? '!' : 'i') }}</span>
        <div class="min-w-0 flex-1">@if($title)<p class="font-semibold">{{ $title }}</p>@endif<div class="{{ $title ? 'mt-1 ' : '' }}text-sm leading-6">{{ $slot }}</div>@isset($action)<div class="mt-3">{{ $action }}</div>@endisset</div>
        @if($dismissible)<button type="button" class="focus-ring rounded p-1 opacity-70 hover:bg-white/10 hover:opacity-100" x-on:click="visible = false" aria-label="Cerrar alerta">✕</button>@endif
    </div>
</div>
