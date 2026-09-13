@props([
    'variant' => 'full',
    'class' => '',
    'alt' => config('app.name'),
])

@php
    $source = match ($variant) {
        'symbol' => asset('images/branding/codered-oni-diamond.png'),
        'square' => asset('images/branding/codered-oni-ceremonial.png'),
        default => asset('images/branding/codered-oni-kamon.png'),
    };
@endphp

<img
    src="{{ $source }}"
    alt="{{ $alt }}"
    {{ $attributes->merge(['class' => trim('block h-auto select-none '.$class)]) }}
>
