<!doctype html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="grid h-full place-items-center bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h1 class="text-4xl font-bold">404</h1>
        <p class="mt-3 text-slate-500">La página solicitada no existe.</p>
    </div>
    @livewireScripts
</body>
</html>
