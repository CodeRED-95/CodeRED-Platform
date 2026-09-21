@props(['name'])

{{--
    Vocabulario de iconos propio del proyecto: SVG a mano, mismo patrón que
    x-ui.status-icon y x-ui.spinner. No introduce una librería externa
    (heroicons/blade-icons) porque ninguna estaba en uso — este componente
    formaliza lo que antes eran emojis sueltos (💾📤📥🗑️...) en las vistas RUC.
--}}
@php
    $aliases = [
        '⌂' => 'home', '◎' => 'database', '⌖' => 'map', '⇪' => 'upload',
        '▣' => 'backup', '⌕' => 'search', '⌗' => 'search', '⚙' => 'settings',
        '⇗' => 'external', '♙' => 'users', '▦' => 'table', '⛁' => 'backup',
        '◇' => 'key', '◈' => 'shield', '◫' => 'refresh', '▤' => 'document',
        '◔' => 'users', '✦' => 'sparkle', '⏱' => 'clock', '↗' => 'external',
    ];
    $name = $aliases[$name] ?? $name;
    $paths = [
        'home' => 'M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-4.75v-6.5h-5.5V21H4.5A1.5 1.5 0 0 1 3 19.5v-9Z',
        'map' => 'm9 18-6 3V6l6-3 6 3 6-3v15l-6 3-6-3Zm0-15v15m6-12v15',
        'search' => 'm20 20-4.5-4.5M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z',
        'settings' => 'M12 15.25A3.25 3.25 0 1 0 12 8.75a3.25 3.25 0 0 0 0 6.5ZM19.4 13.5a7.65 7.65 0 0 0 .05-1.5l2-1.55-2-3.46-2.38.96a7.6 7.6 0 0 0-1.3-.75L15.4 4.7h-4l-.37 2.5a7.6 7.6 0 0 0-1.3.75l-2.38-.96-2 3.46 2 1.55a7.65 7.65 0 0 0 .05 1.5l-2 1.55 2 3.46 2.38-.96c.4.3.84.55 1.3.75l.37 2.5h4l.37-2.5c.46-.2.9-.45 1.3-.75l2.38.96 2-3.46-2-1.55Z',
        'external' => 'M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5',
        'table' => 'M4 5h16v14H4V5Zm0 5h16M10 5v14',
        'key' => 'M15 7a4 4 0 1 0-3.75 5.37L4 19.62V21h2.38l1-1H9l1-1h1.62l1.01-1.01A4 4 0 0 0 15 7Zm1 0h.01',
        'document' => 'M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm6 0v5h5M8.5 13h7M8.5 17h7',
        'sparkle' => 'm12 3 .95 5.05L18 9l-5.05.95L12 15l-.95-5.05L6 9l5.05-.95L12 3Zm6 11 .42 2.08L20.5 16.5l-2.08.42L18 19l-.42-2.08-2.08-.42 2.08-.42L18 14Z',
        'menu' => 'M4 7h16M4 12h16M4 17h16',
        'user' => 'M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'logout' => 'M10 17l5-5-5-5M15 12H3m7-8h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-8',
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
        'eye' => 'M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12Zm9.5 3.5A3.5 3.5 0 1 0 8.5 12a3.5 3.5 0 0 0 3.5 3.5ZM12 10.2A1.8 1.8 0 1 1 10.2 12 1.8 1.8 0 0 1 12 10.2Z',
    ];
    $d = $paths[$name] ?? $paths['warning'];
@endphp

<svg {{ $attributes->class('size-5') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $d }}"/>
</svg>
