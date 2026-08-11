@php
    // Tono de badge por método HTTP, coherente con el Design System.
    $methodTone = fn (string $method): string => [
        'GET' => 'info',
        'POST' => 'brand',
        'PUT' => 'warning',
        'PATCH' => 'warning',
        'DELETE' => 'danger',
    ][$method] ?? 'neutral';
@endphp

<div
    class="space-y-6"
    x-data="{
        active: @js($sections[0]['id'] ?? 'introduccion'),
        mobileNavOpen: false,
        go(id) {
            this.active = id;
            this.mobileNavOpen = false;
            const el = document.getElementById('doc-' + id);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        init() {
            // Resalta la sección visible mientras se hace scroll.
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) this.active = entry.target.id.replace('doc-', '');
                });
            }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });
            this.$el.querySelectorAll('[data-doc-section]').forEach((el) => observer.observe(el));
        },
    }"
>
    <x-ui.page-header title="API CodeRED Platform" subtitle="Documentación de la API v1 de CodeRED Platform: autenticación, agencias, RUC, DNI, Shalom Recordar e integraciones.">
        <x-slot:actions>
            @auth
                @if(auth()->user()->hasPermission('api-tokens.view-any'))
                    <x-ui.button href="{{ route('admin.api-tokens.index') }}" variant="secondary" size="sm">Administrar tokens</x-ui.button>
                @endif
            @endauth
        </x-slot:actions>
    </x-ui.page-header>

    @if(auth()->user()?->isSuperAdmin())
        <section aria-label="Accesos rápidos de documentación" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.card padding="p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-muted)]">Guía interactiva</p>
                <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">Explora la documentación navegable con ejemplos y filtros por categoría.</p>
            </x-ui.card>
            <x-ui.card padding="p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-muted)]">OpenAPI avanzada</p>
                <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">Accede al contrato OpenAPI completo para integraciones y clientes automáticos.</p>
            </x-ui.card>
            <x-ui.card padding="p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-muted)]">Estado de autenticación</p>
                <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">Autoriza un token Bearer y comprueba sus abilities antes de ejecutar solicitudes.</p>
            </x-ui.card>
            <x-ui.card padding="p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-muted)]">Comprobar token</p>
                <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">Usa el endpoint de identidad para validar el token y confirmar su alcance.</p>
            </x-ui.card>
        </section>
    @endif

    <section aria-label="Características de la API" class="flex flex-wrap items-center gap-2">
        <x-ui.badge tone="brand">API v1</x-ui.badge>
        <x-ui.badge tone="neutral">Laravel Sanctum</x-ui.badge>
        <x-ui.badge tone="neutral">{{ $rateLimit }}/min por token</x-ui.badge>
        <x-ui.badge tone="neutral">Máx. {{ $maxPerPage }} por página</x-ui.badge>
        <span class="ml-auto text-xs text-[color:var(--color-text-muted)]">Base URL: <code class="text-[color:var(--color-brand-light)]">{{ $baseUrl }}</code></span>
    </section>

    @if(auth()->user()?->isSuperAdmin())
        <section aria-label="Contrato OpenAPI" class="flex flex-wrap items-center gap-3">
            <x-ui.button href="{{ route('api.docs.spec') }}" variant="secondary" size="sm">Descargar /docs/api/openapi.yaml</x-ui.button>
            <span class="text-xs text-[color:var(--color-text-muted)]">Contrato relativo para navegadores y proxies HTTPS.</span>
        </section>

        <section aria-label="Buscador de endpoints" class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge tone="neutral">Endpoints disponibles</x-ui.badge>
                <x-ui.badge tone="neutral">Buscar endpoint</x-ui.badge>
            </div>
            <div x-data="codeRedApiDocs({ specUrl: @js(route('api.docs.spec')), basePath: '/api/v1' })" x-cloak></div>
            <label class="sr-only" for="api-docs-search">Buscar endpoint</label>
            <input id="api-docs-search" type="search" autocomplete="off" class="sr-only" value="" />
            <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-white/5 p-4 text-sm text-[color:var(--color-text-secondary)]">
                <p>Base de configuración: <code class="text-[color:var(--color-brand-light)]">basePath: '/api/v1'</code></p>
            </div>
        </section>
    @endif

    {{-- Navegación compacta en móvil --}}
    <div class="lg:hidden">
        <x-ui.card padding="p-3">
            <button type="button" class="focus-ring flex w-full items-center justify-between rounded-[var(--radius-control)] px-2 py-2 text-sm font-medium text-white" x-on:click="mobileNavOpen = !mobileNavOpen" x-bind:aria-expanded="mobileNavOpen">
                <span>Índice de la documentación</span>
                <span aria-hidden="true" x-text="mobileNavOpen ? '▲' : '▼'"></span>
            </button>
            <nav x-show="mobileNavOpen" x-cloak x-transition class="mt-2 grid gap-1" aria-label="Secciones (móvil)">
                @foreach ($sections as $section)
                    <button type="button" class="focus-ring rounded-lg px-3 py-2 text-left text-sm" x-on:click="go(@js($section['id']))" x-bind:class="active === @js($section['id']) ? 'bg-[color:var(--color-brand)] text-white' : 'text-[color:var(--color-text-secondary)] hover:bg-white/5'">
                        <span class="mr-2" aria-hidden="true">{{ $section['icon'] }}</span>{{ $section['title'] }}
                    </button>
                @endforeach
            </nav>
        </x-ui.card>
    </div>

    <div class="lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-8">
        {{-- Índice lateral (desktop) --}}
        <aside class="hidden lg:block">
            <nav class="sticky top-6 space-y-1" aria-label="Secciones de la documentación">
                @foreach ($sections as $section)
                    <button type="button"
                        class="focus-ring flex w-full items-center gap-2 rounded-[var(--radius-control)] px-3 py-2 text-left text-sm transition"
                        x-on:click="go(@js($section['id']))"
                        x-bind:class="active === @js($section['id']) ? 'bg-[color:var(--color-brand)] text-white' : 'text-[color:var(--color-text-secondary)] hover:bg-white/5 hover:text-white'"
                        x-bind:aria-current="active === @js($section['id']) ? 'true' : 'false'">
                        <span class="text-base" aria-hidden="true">{{ $section['icon'] }}</span>
                        <span>{{ $section['title'] }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        {{-- Contenido --}}
        <div class="min-w-0 space-y-12">
            @foreach ($sections as $section)
                <section id="doc-{{ $section['id'] }}" data-doc-section class="scroll-mt-6 space-y-5">
                    <div>
                        <h2 class="flex items-center gap-2 font-display text-xl font-semibold text-white">
                            <span aria-hidden="true">{{ $section['icon'] }}</span>{{ $section['title'] }}
                        </h2>
                        <p class="mt-1.5 max-w-3xl text-sm leading-6 text-[color:var(--color-text-secondary)]">{{ $section['description'] }}</p>
                    </div>

                    {{-- Notas / conceptos de la sección --}}
                    @if (! empty($section['notes']))
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($section['notes'] as $note)
                                <x-ui.alert :tone="$note['tone']" :title="$note['title']">{{ $note['body'] }}</x-ui.alert>
                            @endforeach
                        </div>
                    @endif

                    {{-- Tabla de errores comunes (sección Errores) --}}
                    @if (! empty($section['error_table']))
                        <x-ui.card padding="p-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-[color:var(--color-text-muted)]"><tr><th class="px-5 py-3">Código</th><th class="px-5 py-3">Significado</th><th class="px-5 py-3">Descripción</th></tr></thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach ($section['error_table'] as $err)
                                            <tr>
                                                <td class="px-5 py-3"><x-ui.badge :tone="$err['tone']">{{ $err['code'] }}</x-ui.badge></td>
                                                <td class="px-5 py-3 font-medium text-white">{{ $err['title'] }}</td>
                                                <td class="px-5 py-3 text-[color:var(--color-text-secondary)]">{{ $err['description'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-ui.card>
                    @endif

                    {{-- Ejemplos sueltos (sección Errores) --}}
                    @if (! empty($section['examples']))
                        <div class="grid gap-4 md:grid-cols-3">
                            @foreach ($section['examples'] as $example)
                                <div>
                                    <p class="mb-1.5 text-xs font-medium text-[color:var(--color-text-muted)]">{{ $example['title'] }}</p>
                                    <x-docs.code-block :code="$example['code']" language="json" />
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Endpoints de la sección --}}
                    @foreach ($section['endpoints'] as $endpoint)
                        <x-ui.card padding="p-0">
                            <div class="border-b border-[color:var(--color-border)] p-5">
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-ui.badge :tone="$methodTone($endpoint['method'])">{{ $endpoint['method'] }}</x-ui.badge>
                                    <code class="min-w-0 break-all font-mono text-sm text-[color:var(--color-brand-light)]">{{ $endpoint['path'] }}</code>
                                    <span class="ml-auto">
                                        @if ($endpoint['ability'])
                                            <x-ui.badge tone="info">Ability: {{ $endpoint['ability'] }}</x-ui.badge>
                                        @elseif ($endpoint['auth'])
                                            <x-ui.badge tone="neutral">Requiere token</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="success">Público</x-ui.badge>
                                        @endif
                                    </span>
                                </div>
                                <div class="mt-3 flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-white">{{ $endpoint['title'] }}</h3>
                                        <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">{{ $endpoint['description'] }}</p>
                                    </div>
                                    <x-docs.copy-button :text="$endpoint['path']" label="Copiar ruta" />
                                </div>
                            </div>

                            <div class="grid gap-5 p-5 lg:grid-cols-2">
                                {{-- Parámetros --}}
                                <div class="lg:col-span-2">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-muted)]">Parámetros</p>
                                    @if (! empty($endpoint['params']))
                                        <div class="overflow-x-auto rounded-[var(--radius-control)] border border-[color:var(--color-border)]">
                                            <table class="w-full text-left text-xs">
                                                <thead class="bg-white/5 text-[color:var(--color-text-muted)]"><tr><th class="px-3 py-2">Nombre</th><th class="px-3 py-2">Ubicación</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Obligatorio</th><th class="px-3 py-2">Descripción</th></tr></thead>
                                                <tbody class="divide-y divide-white/5">
                                                    @foreach ($endpoint['params'] as $param)
                                                        <tr>
                                                            <td class="px-3 py-2 font-mono text-[color:var(--color-brand-light)]">{{ $param['name'] }}</td>
                                                            <td class="px-3 py-2 text-[color:var(--color-text-secondary)]">{{ $param['in'] }}</td>
                                                            <td class="px-3 py-2 text-[color:var(--color-text-secondary)]">{{ $param['type'] }}</td>
                                                            <td class="px-3 py-2">@if($param['required'])<span class="text-[color:var(--color-danger)]">Sí</span>@else<span class="text-[color:var(--color-text-muted)]">No</span>@endif</td>
                                                            <td class="px-3 py-2 text-[color:var(--color-text-secondary)]">{{ $param['description'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <p class="text-sm text-[color:var(--color-text-muted)]">Este endpoint no requiere parámetros.</p>
                                    @endif
                                </div>

                                {{-- Request --}}
                                <div>
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-muted)]">Ejemplo de request</p>
                                    <x-docs.code-block :code="$endpoint['request']" language="bash" />
                                </div>

                                {{-- Response --}}
                                <div>
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-muted)]">Ejemplo de response</p>
                                    <x-docs.code-block :code="$endpoint['response']" language="json" />
                                </div>

                                {{-- Errores comunes del endpoint --}}
                                @if (! empty($endpoint['errors']))
                                    <div class="lg:col-span-2">
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-text-muted)]">Errores comunes</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($endpoint['errors'] as $code)
                                                <x-ui.badge :tone="$code === '401' || $code === '403' || $code === '500' ? 'danger' : 'warning'">{{ $code }}</x-ui.badge>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </x-ui.card>
                    @endforeach
                </section>
            @endforeach
        </div>
    </div>
</div>
