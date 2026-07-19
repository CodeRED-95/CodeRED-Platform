@props([
    'latitude' => null,
    'longitude' => null,
    'label' => 'Ubicación en el mapa',
    'height' => 'h-72',
])

@php
    $lat = is_numeric($latitude) ? (float) $latitude : null;
    $lng = is_numeric($longitude) ? (float) $longitude : null;
    $hasCoordinates = $lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    $mapUrl = $hasCoordinates
        ? 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
            'bbox' => implode(',', [$lng - 0.015, $lat - 0.009, $lng + 0.015, $lat + 0.009]),
            'layer' => 'mapnik',
            'marker' => $lat.','.$lng,
        ], '', '&', PHP_QUERY_RFC3986)
        : null;
@endphp

@if ($hasCoordinates)
    <figure {{ $attributes->class('overflow-hidden rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background)]') }}>
        <div class="relative {{ $height }}">
            <iframe
                src="{{ $mapUrl }}"
                title="{{ $label }}"
                class="absolute inset-0 h-full w-full border-0"
                loading="lazy"
                referrerpolicy="no-referrer"
            ></iframe>
            <div class="pointer-events-none absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-full" aria-hidden="true">
                <div class="relative flex h-12 w-12 items-center justify-center rounded-full border-2 border-white bg-[color:var(--color-background-elevated)] shadow-2xl">
                    <x-ui.logo variant="symbol" alt="" class="h-9 w-9 rounded-full" />
                    <span class="absolute -bottom-2 h-4 w-4 rotate-45 border-b-2 border-r-2 border-white bg-[color:var(--color-background-elevated)]"></span>
                </div>
            </div>
        </div>
        <figcaption class="flex flex-wrap items-center justify-between gap-2 border-t border-[color:var(--color-border-subtle)] px-4 py-3 text-xs text-[color:var(--color-text-secondary)]">
            <span>{{ $label }}</span>
            <span class="font-mono">{{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}</span>
        </figcaption>
    </figure>
@else
    <x-ui.alert tone="warning" {{ $attributes }}>No hay coordenadas válidas para mostrar el mapa.</x-ui.alert>
@endif
