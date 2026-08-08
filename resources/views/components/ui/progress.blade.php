@props([
    'value' => 0,
    'max' => 100,
    'label' => null,
    'description' => null,
    'showValue' => true,
    'tone' => 'brand',
    'indeterminate' => false,
])

{{--
    value/max representan progreso REAL. Para operaciones sin porcentaje
    conocido (p. ej. un restore síncrono en curso) usar :indeterminate="true"
    en vez de inventar un número — regla del Design System (ver
    /admin/design-system, sección "Reglas UX").
--}}
@php
    $percent = $max > 0 ? max(0, min(100, ($value / $max) * 100)) : 0;
    $fillTones = [
        'brand' => 'bg-[color:var(--color-brand)]',
        'info' => 'bg-[color:var(--color-info)]',
        'success' => 'bg-[color:var(--color-success)]',
        'warning' => 'bg-[color:var(--color-warning)]',
        'danger' => 'bg-[color:var(--color-danger)]',
    ];
    $fillClass = $fillTones[$tone] ?? $fillTones['brand'];
@endphp

<div {{ $attributes }}>
    @if ($label || ($showValue && ! $indeterminate))
        <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
            @if ($label)<span class="font-medium">{{ $label }}</span>@endif
            @if ($showValue && ! $indeterminate)<span class="text-[color:var(--color-text-secondary)]">{{ (int) round($percent) }}%</span>@endif
        </div>
    @endif

    <div
        role="progressbar"
        @unless ($indeterminate)
            aria-valuemin="0"
            aria-valuemax="{{ $max }}"
            aria-valuenow="{{ $value }}"
        @else
            aria-label="{{ $label ?? 'Progreso' }}"
        @endunless
        class="relative h-2 overflow-hidden rounded-full bg-white/10"
    >
        @if ($indeterminate)
            <div class="absolute inset-y-0 left-0 w-1/3 rounded-full {{ $fillClass }}" style="animation: progress-indeterminate 1.4s ease-in-out infinite;"></div>
        @else
            <div class="h-full rounded-full {{ $fillClass }} transition-[width] duration-300" style="width: {{ $percent }}%"></div>
        @endif
    </div>

    @if ($description)
        <p class="mt-1.5 text-xs text-[color:var(--color-text-secondary)]">{{ $description }}</p>
    @endif
</div>
