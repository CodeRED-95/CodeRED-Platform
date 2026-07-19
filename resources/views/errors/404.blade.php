<!doctype html>
<html lang="es" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="code-red-shell grid h-full place-items-center px-4 text-[color:var(--color-text-primary)]">
    <x-ui.card class="w-full max-w-lg text-center" padding="p-10">
        <p class="text-sm font-medium uppercase tracking-[0.28em] text-[color:var(--color-brand-light)]">Error 404</p>
        <h1 class="mt-3 font-display text-4xl font-semibold">Página no encontrada</h1>
        <p class="mt-3 text-[color:var(--color-text-secondary)]">La página solicitada no existe o fue movida.</p>
        <div class="mt-6 flex justify-center">
            <x-ui.button href="{{ url('/') }}" variant="primary">Volver al inicio</x-ui.button>
        </div>
    </x-ui.card>
    @livewireScripts
</body>
</html>
