<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full scroll-smooth" x-data="codeRedSidebar()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/branding/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-dvh overflow-hidden bg-[color:var(--color-background)] text-[color:var(--color-text-primary)]">
    <x-ui.toast-stack :messages="[
        ['tone' => 'success', 'message' => session('success')],
        ['tone' => 'danger', 'message' => session('error')],
    ]" />
    <div class="code-red-shell h-dvh min-h-0 overflow-hidden">
        <div class="flex h-dvh min-h-0 overflow-hidden">
            <aside class="layer-sidebar fixed inset-y-0 left-0 hidden h-dvh min-h-0 flex-col overflow-hidden border-r border-[color:var(--color-border-subtle)] bg-[color:var(--color-sidebar)]/95 backdrop-blur lg:flex transition-[width] duration-200 ease-in-out"
                   :class="desktopCollapsed ? 'lg:w-[76px]' : 'lg:w-[264px]'">
                <div class="relative shrink-0 border-b border-white/5" :class="desktopCollapsed ? 'px-3' : 'px-6 py-6'">
                    <div class="flex items-center" :class="desktopCollapsed ? 'justify-center py-6' : ''">
                        <x-ui.logo variant="symbol" class="h-11 w-11 rounded-2xl shrink-0" />
                        <div class="ml-4" x-show="!desktopCollapsed" x-cloak>
                            <p class="text-lg font-semibold tracking-tight">CodeRED Platform</p>
                            <p class="text-sm text-[color:var(--color-text-secondary)]">Plataforma modular</p>
                        </div>
                    </div>
                    <div x-show="!desktopCollapsed" x-cloak class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2">
                        <button
                            type="button"
                            class="focus-ring flex h-7 w-7 items-center justify-center rounded-full border border-white/10 bg-[color:var(--color-sidebar)] text-[color:var(--color-text-muted)] transition hover:scale-110 hover:text-white"
                            x-on:click="toggleDesktop"
                            aria-label="Toggle sidebar"
                            :aria-expanded="!desktopCollapsed"
                        >
                            <svg class="h-4 w-4 transition-transform" viewBox="0 0 20 20" fill="none" aria-hidden="true" :class="desktopCollapsed ? 'rotate-180' : ''"><path d="m7 15 5-5-5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                </div>

                <nav
                    class="sidebar-navigation min-h-0 flex-1 overflow-y-auto overscroll-contain text-sm [scrollbar-gutter:stable]"
                    :class="desktopCollapsed ? 'px-2 py-5' : 'px-4 py-5'"
                    data-sidebar-navigation
                    x-data="codeRedSidebarScroll"
                    x-on:scroll.passive="remember"
                >
                    @php
                        $navGroups = [
                            'General' => [
                                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => '⌂', 'can' => auth()->user()->hasPermission('dashboard.view')],
                            ],
                            'Agencias' => [
                                ['label' => 'Listado', 'route' => 'admin.agencies.index', 'icon' => '◎', 'can' => Gate::allows('viewAny', \App\Modules\Agencies\Models\Agency::class)],
                                ['label' => 'Mapa de agencias', 'route' => 'admin.agencies.map', 'icon' => '⌖', 'can' => Gate::allows('viewAny', \App\Modules\Agencies\Models\Agency::class)],
                                ['label' => 'Importar', 'route' => 'admin.agencies.import', 'icon' => '⇪', 'can' => Gate::allows('import', \App\Modules\Agencies\Models\Agency::class)],
                                ['label' => 'Copias de seguridad', 'route' => 'admin.agencies.backups.index', 'icon' => '▣', 'can' => auth()->user()->hasPermission('agencies.backup.view')],
                            ],
                            'Identidad' => [
                                ['label' => 'Probar API DNI', 'route' => 'admin.api-tools.dni', 'icon' => '⌕', 'can' => auth()->user()->hasPermission('api-tools.dni.test')],
                                ['label' => 'Configuración DNI', 'route' => 'admin.settings.dni', 'icon' => '⚙', 'can' => auth()->user()->hasPermission('settings.dni.view')],
                            ],
                            'Empresas y RUC' => [
                                ['label' => 'Probar API RUC', 'route' => 'admin.api-tools.ruc', 'icon' => '⌕', 'can' => auth()->user()->hasPermission('ruc.test')],
                                ['label' => 'Padrón RUC', 'route' => 'admin.ruc.records', 'icon' => '▦', 'can' => auth()->user()->hasPermission('ruc.view')],
                                ['label' => 'Importaciones RUC', 'route' => 'admin.ruc.imports', 'icon' => '⇪', 'can' => auth()->user()->hasPermission('ruc.import-history')],
                            ],
                            'API' => [
                                ['label' => 'Tokens', 'route' => 'admin.api-tokens.index', 'icon' => '◇', 'can' => auth()->user()->hasPermission('api-tokens.view-any')],
                                ['label' => 'Documentación', 'route' => 'api.docs', 'icon' => '▤', 'can' => true],
                            ],
                            'Administración' => [
                                ['label' => 'Usuarios', 'route' => 'admin.users.index', 'icon' => '◔', 'can' => Gate::allows('viewAny', \App\Models\User::class)],
                                ['label' => 'Design System', 'route' => 'admin.design-system', 'icon' => '✦', 'can' => auth()->user()->isSuperAdmin()],
                            ],
                            'Configuración' => [
                                ['label' => 'Documentación API', 'route' => 'admin.settings.api-documentation', 'icon' => '⚙', 'can' => auth()->user()->hasPermission('settings.api-documentation.update')],
                                ['label' => 'Copias de agencias', 'route' => 'admin.settings.agency-backups', 'icon' => '⚙', 'can' => auth()->user()->hasPermission('settings.agency-backups.update')],
                                ['label' => 'Ubigeos', 'route' => 'admin.settings.ubigeos', 'icon' => '⌖', 'can' => auth()->user()->hasPermission('settings.ubigeos.update')],
                            ],
                        ];
                    @endphp

                    <div class="flex flex-col gap-y-1">
                    @foreach ($navGroups as $group => $items)
                        @if(collect($items)->contains('can', true))
                        <div class="space-y-1">
                            <p class="px-3 pb-1 pt-4 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--color-text-muted)] first:pt-0" x-show="!desktopCollapsed" x-cloak>
                                <span>{{ $group }}</span>
                            </p>
                            @foreach ($items as $item)
                              @if ($item['can'])
                                <a href="{{ route($item['route']) }}"
                                   :title="desktopCollapsed ? '{{ $item['label'] }}' : ''"
                                   @if(request()->routeIs($item['route'])) data-sidebar-active="true" aria-current="page" @endif
                                   :class="[
                                       'sidebar-link',
                                       desktopCollapsed ? 'lg:justify-center' : ''
                                   ]"
                                   >
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/5 text-[color:var(--color-brand-light)] shrink-0">{{ $item['icon'] }}</span>
                                    <span class="font-medium" x-show="!desktopCollapsed" x-cloak>{{ $item['label'] }}</span>
                                </a>
                              @endif
                            @endforeach
                        </div>
                        @endif
                    @endforeach
                    </div>
                </nav>

                <div class="mt-auto shrink-0 border-t border-white/5" :class="desktopCollapsed ? 'p-2' : 'p-4'">
                    <a href="{{ route('profile.show') }}" aria-label="Abrir mi perfil" :title="desktopCollapsed ? '{{ auth()->user()->name }}' : ''"
                       :class="[
                           'focus-ring flex items-center gap-3 rounded-2xl bg-white/5 p-3 transition hover:bg-white/10',
                           desktopCollapsed ? 'lg:justify-center' : ''
                       ]">
                        <x-ui.avatar :name="auth()->user()->name" size="sm" class="shrink-0"/>
                        <div class="min-w-0 flex-1" x-show="!desktopCollapsed" x-cloak>
                            <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-[color:var(--color-text-secondary)]">{{ auth()->user()->email }}</p>
                        </div>
                        <svg class="size-4 shrink-0 text-[color:var(--color-text-muted)]" viewBox="0 0 20 20" fill="none" aria-hidden="true" x-show="!desktopCollapsed" x-cloak><path d="m8 5 5 5-5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <form method="post" action="{{ route('logout') }}" :class="desktopCollapsed ? 'mt-2' : 'mt-3'">
                        @csrf
                        <button type="submit" :title="desktopCollapsed ? 'Cerrar sesión' : ''"
                                :class="[
                                    'focus-ring flex h-10 w-full items-center justify-center rounded-lg bg-white/5 px-4 text-sm font-semibold text-white/70 transition hover:bg-white/10 hover:text-white',
                                    desktopCollapsed ? 'lg:aspect-square' : ''
                                ]">
                            <span x-show="!desktopCollapsed">Cerrar sesión</span>
                            <svg x-show="desktopCollapsed" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 12H3m0 0 3.5-3.5M3 12l3.5 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex h-dvh min-h-0 flex-1 flex-col overflow-hidden ease-in-out transition-[padding-left] duration-200"
                 :class="desktopCollapsed ? 'lg:pl-[76px]' : 'lg:pl-[264px]'">
                <header class="layer-header shrink-0 border-b border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/90 backdrop-blur">
                    <div class="flex items-center justify-between gap-4 px-4 py-4 lg:px-8">
                        <div class="flex items-center gap-3">
                            <x-ui.icon-button class="h-11 w-11 lg:hidden" x-on:click="mobileOpen = true" label="Abrir menú">
                                <span class="text-xl">☰</span>
                            </x-ui.icon-button>
                            <div>
                                <p class="text-xs uppercase tracking-[0.24em] text-[color:var(--color-text-muted)]">CodeRED Platform</p>
                                <h1 class="font-display text-lg font-semibold text-white">{{ $pageTitle ?? 'Panel administrativo' }}</h1>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @auth
                                <a href="{{ route('profile.show') }}" aria-label="Abrir mi perfil" class="focus-ring rounded-full"><x-ui.avatar :name="auth()->user()->name" size="sm" /></a>
                            @endauth
                        </div>
                    </div>
                </header>

                <main class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-6 lg:px-8 lg:py-8" id="main-content">
                    <div class="mx-auto w-full max-w-[1680px]">{{ $slot }}</div>
                </main>
            </div>
        </div>

        <div x-cloak x-show="mobileOpen" class="layer-popover fixed inset-0 h-dvh overflow-hidden lg:hidden">
            <div class="absolute inset-0 bg-black/70" x-on:click="mobileOpen = false"></div>
            <aside class="absolute inset-y-0 left-0 flex h-dvh min-h-0 w-[86vw] max-w-sm flex-col overflow-hidden border-r border-white/10 bg-[color:var(--color-sidebar)] shadow-2xl">
                <div class="flex shrink-0 items-center justify-between p-5 pb-0">
                    <x-ui.logo variant="symbol" class="h-10 w-10 rounded-xl" />
                    <x-ui.icon-button x-on:click="mobileOpen = false" label="Cerrar menú">✕</x-ui.icon-button>
                </div>
                <div class="mx-5 mt-5 shrink-0 border-b border-white/5 pb-4">
                    <a href="{{ route('profile.show') }}" class="focus-ring flex items-center gap-3 rounded-2xl bg-white/5 p-3">
                        <x-ui.avatar :name="auth()->user()->name" size="sm" />
                        <div class="min-w-0"><p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p><p class="truncate text-xs text-[color:var(--color-text-secondary)]">Mi perfil</p></div>
                    </a>
                </div>
                <nav class="sidebar-navigation mt-5 min-h-0 flex-1 space-y-2 overflow-y-auto overscroll-contain px-5 pb-5 [scrollbar-gutter:stable]" data-sidebar-navigation x-data="codeRedSidebarScroll" x-on:scroll.passive="remember" x-on:click="if ($event.target.closest('a')) mobileOpen = false">
                    @foreach ($navGroups as $group => $items)
                        @if(collect($items)->contains('can', true))<p class="px-4 pb-1 pt-3 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--color-text-muted)] first:pt-0">{{ $group }}</p>@endif
                        @foreach ($items as $item)
                            @if($item['can'])
                                <a
                                    href="{{ route($item['route']) }}"
                                    @if(request()->routeIs($item['route'])) data-sidebar-active="true" aria-current="page" @endif
                                    @class([
                                        'sidebar-link',
                                    ])
                                >{{ $item['label'] }}</a>
                            @endif
                        @endforeach
                    @endforeach
                </nav>
                <form method="post" action="{{ route('logout') }}" class="shrink-0 border-t border-white/5 p-5">
                    @csrf
                    <x-ui.button variant="secondary" size="sm" type="submit" class="w-full">Cerrar sesión</x-ui.button>
                </form>
            </aside>
        </div>
    </div>
    @livewireScripts
</body>
</html>