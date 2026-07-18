<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" x-data="{ dark: localStorage.theme === 'dark' }" x-bind:class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="min-h-screen flex">
        <aside class="hidden lg:flex w-72 flex-col border-r border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
            <div class="p-6 text-lg font-semibold tracking-tight">CodeRED Platform</div>
            <nav class="px-4 space-y-1 text-sm">
                <a class="block rounded-lg px-3 py-2 bg-slate-100 dark:bg-slate-800" href="{{ route('dashboard') }}">Dashboard</a>
                @can('viewAny', \App\Modules\Agencies\Models\Agency::class)
                    <a class="block rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" href="{{ route('admin.agencies.index') }}">Agencias</a>
                    <a class="block rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" href="{{ route('public.agencies.index') }}">Pública</a>
                @endcan
            </nav>
        </aside>
        <div class="flex-1 flex flex-col">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white/80 px-4 py-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80 lg:px-8">
                <div>
                    <h1 class="text-base font-semibold">{{ config('app.name') }}</h1>
                    <p class="text-sm text-slate-500">Plataforma modular</p>
                </div>
                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('admin.agencies.create') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">Nueva agencia</a>
                    @endauth
                    <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700" x-on:click="dark = !dark; localStorage.theme = dark ? 'dark' : 'light'">Tema</button>
                </div>
            </header>
            <main class="flex-1 p-4 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
