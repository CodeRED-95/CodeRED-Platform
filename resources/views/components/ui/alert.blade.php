@props(['tone' => 'info'])

@php
    $tones = [
        'info' => 'border-sky-500/20 bg-sky-500/10 text-sky-100',
        'success' => 'border-emerald-500/20 bg-emerald-500/10 text-emerald-100',
        'warning' => 'border-amber-500/20 bg-amber-500/10 text-amber-100',
        'danger' => 'border-rose-500/20 bg-rose-500/10 text-rose-100',
        'brand' => 'border-[color:var(--color-brand-soft)] bg-[color:var(--color-brand-soft)] text-white',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] border p-4 '.($tones[$tone] ?? $tones['info'])]) }}>
    {{ $slot }}
</div>
