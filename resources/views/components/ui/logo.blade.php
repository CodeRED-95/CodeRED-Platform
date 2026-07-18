@props([
    'variant' => 'full',
    'class' => '',
    'alt' => config('app.name'),
])

@php
    $source = match ($variant) {
        'symbol' => asset('images/branding/codered-symbol.png'),
        'square' => asset('images/branding/codered-square.png'),
        default => asset('images/branding/codered-logo-full.png'),
    };
@endphp

<img
    src="{{ $source }}"
    alt="{{ $alt }}"
    {{ $attributes->merge(['class' => trim('block h-auto select-none '.$class)]) }}
>
