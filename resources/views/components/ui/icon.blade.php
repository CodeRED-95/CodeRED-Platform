@props(['name'])

{{--
    Vocabulario de iconos propio del proyecto: SVG a mano, mismo patrón que
    x-ui.status-icon y x-ui.spinner. No introduce una librería externa
    (heroicons/blade-icons) porque ninguna estaba en uso — este componente
    formaliza lo que antes eran emojis sueltos (💾📤📥🗑️...) en las vistas RUC.
--}}
@php
    $paths = [
        'upload' => 'M12 16V4m0 0-4 4m4-4 4 4M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2',
        'download' => 'M12 4v12m0 0-4-4m4 4 4-4M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2',
        'database' => 'M12 5c4.418 0 8-1.12 8-2.5S16.418 0 12 0 4 1.12 4 2.5 7.582 5 12 5Zm0 0v14.5c0 1.38 3.582 2.5 8 2.5s8-1.12 8-2.5V5M4 2.5V17c0 1.38 3.582 2.5 8 2.5m-8-9.75C4 10.63 7.582 11.75 12 11.75',
        'backup' => 'M4 7a2 2 0 0 1 2-2h3l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm8 3v5m0 0-2.25-2.25M12 15l2.25-2.25',
        'restore' => 'M4 4v5h5M4.5 15a8 8 0 1 0 2-8.5L4 9',
        'trash' => 'M6 7h12M9.5 7V5a1.5 1.5 0 0 1 1.5-1.5h2A1.5 1.5 0 0 1 14.5 5v2M7.5 7l.7 11.2a2 2 0 0 0 2 1.8h3.6a2 2 0 0 0 2-1.8L16.5 7',
        'warning' => 'M12 9v4m0 4h.01M10.3 3.86 2.3 18a1.5 1.5 0 0 0 1.3 2.25h16.8A1.5 1.5 0 0 0 21.7 18l-8-14.14a1.5 1.5 0 0 0-2.6 0Z',
        'success' => 'm5 13 4.5 4.5L19 8',
        'error' => 'M6 6l12 12M18 6 6 18',
        'clock' => 'M12 7v5l3.5 2M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
        'info' => 'M12 16v-4.5M12 8h.01M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
        'shield' => 'M12 3.5 5 6v5.2c0 4.4 3 7.6 7 8.8 4-1.2 7-4.4 7-8.8V6l-7-2.5Z',
        'check' => 'm5 13 4.5 4.5L19 8',
        'x' => 'M6 6l12 12M18 6 6 18',
        'refresh' => 'M4 4v5h5M20 20v-5h-5M4.5 9a8 8 0 0 1 14.5-3.5M19.5 15a8 8 0 0 1-14.5 3.5',
        'inbox' => 'M4 12h4l1.5 3h5L16 12h4M4 12l1.5-6.5A2 2 0 0 1 7.44 4h9.12a2 2 0 0 1 1.94 1.5L20 12M4 12v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5',
    ];
    $d = $paths[$name] ?? $paths['warning'];
@endphp

<svg {{ $attributes->class('size-5') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $d }}"/>
</svg>
