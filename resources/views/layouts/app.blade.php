<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth" x-data="{ sidebarOpen: false, theme: localStorage.theme ?? 'dark' }" x-init="$nextTick(() => { document.documentElement.classList.toggle('dark', theme === 'dark') })" x-effect="document.documentElement.classList.toggle('dark', theme === 'dark')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/branding/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-[color:var(--color-background)] text-[color:var(--color-text-primary)]">
    <div class="min-h-screen code-red-shell">
        <div class="flex min-h-screen">
            <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-[color:var(--color-border-subtle)] bg-[color:var(--color-sidebar)]/95 backdrop-blur lg:flex lg:flex-col">
                <div class="border-b border-white/5 px-6 py-6">
                    <x-ui.logo variant="symbol" class="h-11 w-11 rounded-2xl" />
                    <div class="mt-4">
                        <p class="text-lg font-semibold tracking-tight">CodeRED Platform</p>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">Plataforma modular</p>
                    </div>
                </div>

                <nav class="flex-1 space-y-1 px-4 py-5 text-sm">
                    @php
                        $nav = [
                            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => '⌂', 'can' => true],
                            ['label' => 'Agencias', 'route' => 'admin.agencies.index', 'icon' => '◎', 'can' => \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Modules\Agencies\Models\Agency::class)],
                            ['label' => 'Importaciones', 'route' => 'admin.agencies.import', 'icon' => '⇪', 'can' => \Illuminate\Support\Facades\Gate::allows('import', \App\Modules\Agencies\Models\Agency::class)],
                            ['label' => 'Design System', 'route' => 'admin.design-system', 'icon' => '✦', 'can' => app()->environment('local') || \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Modules\Agencies\Models\Agency::class)],
                            ['label' => 'Usuarios', 'route' => 'admin.users.index', 'icon' => '◔', 'can' => \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\User::class)],
                            ['label' => 'Roles y permisos', 'route' => null, 'icon' => '◌', 'can' => false],
                            ['label' => 'Auditoría', 'route' => null, 'icon' => '▤', 'can' => false],
                            ['label' => 'Configuración', 'route' => null, 'icon' => '⚙', 'can' => false],
                        ];
                    @endphp

                    @foreach ($nav as $item)
                        @if ($item['can'])
                            <a href="{{ route($item['route']) }}"
                               @class([
                                   'flex items-center gap-3 rounded-2xl px-3 py-3 transition focus-ring',
                                   'bg-white/10 text-white shadow-sm ring-1 ring-inset ring-white/10' => request()->routeIs($item['route']),
                                   'text-[color:var(--color-text-secondary)] hover:bg-white/5 hover:text-white' => ! request()->routeIs($item['route']),
                               ])>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/5 text-[color:var(--color-brand-light)]">{{ $item['icon'] }}</span>
                                <span class="font-medium">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </nav>

                @auth
                    <div class="border-t border-white/5 p-4">
                        <div class="flex items-center gap-3 rounded-2xl bg-white/5 p-3">
                            <x-ui.avatar :name="auth()->user()->name" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-[color:var(--color-text-secondary)]">{{ auth()->user()->email }}</p>
                            </div>
                        </div>
                        <form method="post" action="{{ route('logout') }}" class="mt-3">
                            @csrf
                            <x-ui.button variant="secondary" size="sm" type="submit" class="w-full">Cerrar sesión</x-ui.button>
                        </form>
                    </div>
                @endauth
            </aside>

            <div class="flex min-h-screen flex-1 flex-col lg:pl-72">
                <header class="sticky top-0 z-30 border-b border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/90 backdrop-blur">
                    <div class="flex items-center justify-between gap-4 px-4 py-4 lg:px-8">
                        <div class="flex items-center gap-3">
                            <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-[color:var(--color-border)] bg-white/5 lg:hidden focus-ring" x-on:click="sidebarOpen = true" aria-label="Abrir menú">
                                <span class="text-xl">☰</span>
                            </button>
                            <div>
                                <p class="text-xs uppercase tracking-[0.24em] text-[color:var(--color-text-muted)]">CodeRED Platform</p>
                                <h1 class="font-display text-lg font-semibold text-white">{{ $pageTitle ?? config('app.name') }}</h1>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" class="hidden rounded-2xl border border-[color:var(--color-border)] bg-white/5 px-3 py-2 text-sm text-[color:var(--color-text-secondary)] lg:inline-flex focus-ring">Búsqueda global</button>
                            <button type="button" class="rounded-2xl border border-[color:var(--color-border)] bg-white/5 px-3 py-2 text-sm focus-ring" x-on:click="theme = theme === 'dark' ? 'light' : 'dark'; localStorage.theme = theme">
                                Tema
                            </button>
                            @auth
                                <x-ui.avatar :name="auth()->user()->name" size="sm" />
                            @endauth
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-black/70" x-on:click="sidebarOpen = false"></div>
            <aside class="absolute inset-y-0 left-0 w-[86vw] max-w-sm border-r border-white/10 bg-[color:var(--color-sidebar)] p-5 shadow-2xl">
                <div class="flex items-center justify-between">
                    <x-ui.logo variant="symbol" class="h-10 w-10 rounded-xl" />
                    <button type="button" class="rounded-xl p-2 focus-ring" x-on:click="sidebarOpen = false" aria-label="Cerrar menú">✕</button>
                </div>
                <div class="mt-5 space-y-2">
                    <a href="{{ route('dashboard') }}" class="block rounded-2xl bg-white/5 px-4 py-3">Dashboard</a>
                    @can('viewAny', \App\Modules\Agencies\Models\Agency::class)
                        <a href="{{ route('admin.agencies.index') }}" class="block rounded-2xl px-4 py-3 text-[color:var(--color-text-secondary)]">Agencias</a>
                    @endcan
                    @can('import', \App\Modules\Agencies\Models\Agency::class)
                        <a href="{{ route('admin.agencies.import') }}" class="block rounded-2xl px-4 py-3 text-[color:var(--color-text-secondary)]">Importaciones</a>
                    @endcan
                    @can('viewAny', \App\Models\User::class)
                        <a href="{{ route('admin.users.index') }}" class="block rounded-2xl px-4 py-3 text-[color:var(--color-text-secondary)]">Usuarios</a>
                    @endcan
                </div>
            </aside>
        </div>
    </div>
    @livewireScripts
</body>
</html>
