@props([
    'latitude' => null,
    'longitude' => null,
    'label' => 'Ubicación en el mapa',
    'name' => 'Agencia CodeRED',
    'location' => '',
])

@php
    $lat = is_numeric($latitude) ? (float) $latitude : null;
    $lng = is_numeric($longitude) ? (float) $longitude : null;
    $hasCoordinates = $lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    $googleUrl = $hasCoordinates ? 'https://www.google.com/maps/search/?api=1&query='.$lat.','.$lng : null;
@endphp

@if ($hasCoordinates)
    <figure
        wire:key="map-{{ $lat }}-{{ $lng }}"
        x-data="codeRedMap(@js([
            'latitude' => $lat,
            'longitude' => $lng,
            'name' => $name,
            'location' => $location,
            'markerUrl' => asset('images/branding/codered-symbol.png'),
            'googleUrl' => $googleUrl,
        ]))"
        x-on:codered-map:destroy.window="destroy()"
        {{ $attributes->class('overflow-hidden rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background)]') }}
    >
        <div
            x-ref="map"
            wire:ignore
            data-codered-map
            class="h-[260px] w-full bg-[color:var(--color-surface)] lg:h-[340px]"
            role="region"
            aria-label="{{ $label }}"
        ></div>
        <figcaption class="flex flex-wrap items-center justify-between gap-2 border-t border-[color:var(--color-border-subtle)] px-4 py-3 text-xs text-[color:var(--color-text-secondary)]">
            <span>{{ $label }}</span>
            <span class="font-mono">{{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}</span>
        </figcaption>
    </figure>
@else
    <x-ui.alert tone="warning" {{ $attributes }}>Esta agencia todavía no tiene coordenadas registradas.</x-ui.alert>
@endif
