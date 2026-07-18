@props(['href' => null, 'label' => 'Acción'])

@php
    $base = 'inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-text-primary)] transition hover:bg-[color:var(--color-surface-hover)] focus-ring';
@endphp

@if ($href)
    <a href="{{ $href }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $base]) }}>{{ $slot }}</a>
@else
    <button type="button" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $base]) }}>{{ $slot }}</button>
@endif
