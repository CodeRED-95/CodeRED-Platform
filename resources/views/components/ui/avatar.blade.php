@props(['name' => null, 'src' => null, 'size' => 'md'])

@php
    $sizes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-12 w-12 text-base'];
@endphp

<div {{ $attributes->merge(['class' => trim('inline-flex items-center justify-center overflow-hidden rounded-full bg-white/10 font-semibold text-white '.($sizes[$size] ?? $sizes['md']))]) }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="h-full w-full object-cover">
    @else
        {{ mb_strtoupper(mb_substr((string) $name, 0, 1)) }}
    @endif
</div>
