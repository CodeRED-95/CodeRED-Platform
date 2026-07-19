<div
    class="space-y-6"
    x-data="codeRedApiDocs({ specUrl: @js(route('api.docs.spec', absolute: false)), basePath: '/api/v1' })"
>
    <x-ui.page-header title="API CodeRED Platform" subtitle="Consulta y sincroniza el catálogo oficial de agencias mediante tokens seguros.">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.api-tokens.index') }}" variant="secondary" size="sm">Administrar tokens</x-ui.button>
            <x-ui.button href="{{ route('api.docs.spec', absolute: false) }}" variant="outline" size="sm">Descargar OpenAPI</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <section aria-label="Estado y características de la API" class="flex flex-wrap items-center gap-2">
        <x-ui.badge tone="brand">API v1</x-ui.badge>
        <x-ui.badge tone="info">OpenAPI 3.0.3</x-ui.badge>
        <x-ui.badge tone="success">Solo lectura</x-ui.badge>
        <x-ui.badge tone="neutral">Laravel Sanctum</x-ui.badge>
        <x-ui.badge tone="neutral">{{ $rateLimit }}/minuto/token</x-ui.badge>
        <x-ui.badge tone="neutral">Máximo {{ $maxPerPage }} por página</x-ui.badge>
        <span class="ml-auto text-xs text-[color:var(--color-text-muted)]">Base URL: <code x-text="apiBaseUrl"></code></span>
    </section>

    <nav aria-label="Vistas de documentación" role="tablist" class="flex flex-wrap gap-2 border-b border-[color:var(--color-border)] pb-3">
        <button type="button" role="tab" x-bind:aria-selected="activeTab === 'guide'" class="focus-ring rounded-[var(--radius-control)] px-4 py-2 text-sm font-medium" x-bind:class="activeTab === 'guide' ? 'bg-[color:var(--color-brand)] text-white' : 'bg-white/5 text-[color:var(--color-text-secondary)] hover:text-white'" x-on:click="switchTab('guide')">Guía interactiva</button>
        <button type="button" role="tab" x-bind:aria-selected="activeTab === 'openapi'" class="focus-ring rounded-[var(--radius-control)] px-4 py-2 text-sm font-medium" x-bind:class="activeTab === 'openapi' ? 'bg-[color:var(--color-brand)] text-white' : 'bg-white/5 text-[color:var(--color-text-secondary)] hover:text-white'" x-on:click="switchTab('openapi')">OpenAPI avanzada</button>
        <button type="button" role="tab" x-bind:aria-selected="activeTab === 'schemas'" class="focus-ring rounded-[var(--radius-control)] px-4 py-2 text-sm font-medium" x-bind:class="activeTab === 'schemas' ? 'bg-[color:var(--color-brand)] text-white' : 'bg-white/5 text-[color:var(--color-text-secondary)] hover:text-white'" x-on:click="switchTab('schemas')">Esquemas</button>
    </nav>

    <div x-show="activeTab === 'guide'" class="space-y-6">
        <x-ui.card padding="p-5" class="border border-[color:var(--color-brand-soft)]">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-lg font-semibold text-white">Autenticación</h2>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1" x-bind:class="authStatus === 'valid' ? 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20' : authStatus === 'invalid' || authStatus === 'forbidden' ? 'bg-rose-500/10 text-rose-200 ring-rose-500/20' : 'bg-white/5 text-[color:var(--color-text-secondary)] ring-white/10'" x-text="authMessage"></span>
                    </div>
                    <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">El token permanece únicamente en memoria mientras esta página está abierta. Nunca se guarda en storage, cookies o ejemplos.</p>
                    <label for="api-docs-token" class="mt-4 block text-sm font-medium text-white">Bearer Token</label>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row">
                        <div class="relative min-w-0 flex-1">
                            <input id="api-docs-token" x-bind:type="showToken ? 'text' : 'password'" x-model="token" autocomplete="off" spellcheck="false" class="min-h-[var(--control-height)] w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-4 pr-24 text-sm text-white focus-ring" placeholder="Pega el token Sanctum">
                            <button type="button" class="focus-ring absolute inset-y-1 right-1 rounded-lg px-3 text-xs text-[color:var(--color-text-secondary)] hover:text-white" x-on:click="showToken = !showToken" x-text="showToken ? 'Ocultar' : 'Mostrar'"></button>
                        </div>
                        <x-ui.button type="button" x-on:click="authorize" x-bind:disabled="authStatus === 'loading'">Autorizar</x-ui.button>
                        <x-ui.button type="button" variant="secondary" x-on:click="clearAuthorization">Limpiar token</x-ui.button>
                    </div>
                    <p class="mt-2 text-xs text-[color:var(--color-text-muted)]" aria-live="polite" x-text="authStatus === 'loading' ? 'Validando token…' : authMessage"></p>
                    <p x-show="authDetails?.abilities" class="mt-2 text-xs text-[color:var(--color-text-secondary)]">Abilities: <span class="text-white" x-text="authDetails?.abilities?.join(', ')"></span></p>
                </div>
                <x-ui.button type="button" variant="outline" size="sm" x-on:click="execute(endpoints.find((item) => item.id === 'health'))">Comprobar servicio</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-5">
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_14rem]">
                <x-ui.search-box label="Buscar endpoint" placeholder="Ruta, nombre, categoría o ability…" x-model="search" />
                <fieldset><legend class="text-sm font-medium text-white">Categoría</legend><div class="mt-1.5 flex flex-wrap gap-1"><template x-for="filter in ['Todos','Sistema','Autenticación','Agencias','Catálogo']" x-bind:key="filter"><button type="button" class="focus-ring rounded-lg px-2.5 py-2 text-xs" x-bind:class="categoryFilter === filter ? 'bg-[color:var(--color-brand)] text-white' : 'bg-white/5 text-[color:var(--color-text-secondary)] hover:text-white'" x-on:click="categoryFilter = filter" x-text="filter"></button></template></div></fieldset>
            </div>
        </x-ui.card>

        <template x-for="category in ['Sistema', 'Autenticación', 'Agencias', 'Catálogo']" x-bind:key="category">
            <section x-show="categoryEndpoints(category).length" class="space-y-3" x-bind:aria-labelledby="'api-category-' + category.replaceAll(' ', '-')">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white" x-bind:id="'api-category-' + category.replaceAll(' ', '-')" x-text="category"></h2>
                        <p class="text-sm text-[color:var(--color-text-secondary)]" x-text="categoryDescription(category)"></p>
                    </div>
                    <x-ui.badge tone="neutral"><span x-text="categoryEndpoints(category).length"></span>&nbsp;endpoints</x-ui.badge>
                </div>

                <div class="grid items-start gap-4 xl:grid-cols-2">
                    <template x-for="endpoint in categoryEndpoints(category)" x-bind:key="endpoint.id">
                        <article class="glass-panel overflow-visible rounded-[var(--radius-card)] border border-[color:var(--color-border)]">
                            <div class="p-5">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 rounded-md bg-sky-500/10 px-2 py-1 text-xs font-bold text-sky-300 ring-1 ring-sky-500/20" x-text="endpoint.method"></span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-white" x-text="endpoint.title"></h3>
                                        <code class="mt-1 block break-all text-xs text-[color:var(--color-brand-light)]" x-text="endpoint.fullPath"></code>
                                    </div>
                                    <span class="rounded-full bg-white/5 px-2 py-1 text-[11px] text-[color:var(--color-text-secondary)]" x-text="endpoint.ability"></span>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-[color:var(--color-text-secondary)]" x-text="endpoint.description"></p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <x-ui.button type="button" size="sm" variant="secondary" x-on:click="endpoint.expanded = !endpoint.expanded" x-bind:aria-expanded="endpoint.expanded" x-text="endpoint.expanded ? 'Ocultar detalles' : 'Ver detalles'"></x-ui.button>
                                    <x-ui.button type="button" size="sm" x-on:click="execute(endpoint)" x-bind:disabled="endpoint.loading"><span x-text="endpoint.loading ? 'Ejecutando…' : 'Probar endpoint'"></span></x-ui.button>
                                    <x-ui.button type="button" size="sm" variant="ghost" x-on:click="copy(endpoint.fullPath, 'Ruta')">Copiar ruta</x-ui.button>
                                </div>
                            </div>

                            <div x-show="endpoint.expanded" class="border-t border-[color:var(--color-border)] p-5">
                                <div x-show="endpoint.parameters.length" class="grid gap-4 sm:grid-cols-2">
                                    <template x-for="parameter in endpoint.parameters" x-bind:key="parameter.name">
                                        <label class="block text-sm text-white">
                                            <span x-text="parameter.name"></span><span x-show="parameter.required" class="text-rose-300"> *</span>
                                            <span class="ml-1 text-xs text-[color:var(--color-text-muted)]" x-text="parameter.in"></span>
                                            <template x-if="parameter.inputType === 'select'"><span class="relative mt-1.5 block" x-data="{ open: false }"><button type="button" class="focus-ring flex min-h-[var(--control-height)] w-full items-center justify-between rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-3 text-sm text-white" x-on:click="open = !open" x-bind:aria-expanded="open"><span x-text="endpoint.values[parameter.name] || 'Sin filtro'"></span><span aria-hidden="true">⌄</span></button><span x-cloak x-show="open" x-on:click.outside="open = false" class="layer-popover absolute z-50 mt-1 max-h-52 w-full overflow-auto rounded-xl border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-1 shadow-xl"><button type="button" class="focus-ring block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-200 hover:bg-white/10" x-on:click="endpoint.values[parameter.name] = ''; open = false">Sin filtro</button><template x-for="option in parameter.schema.enum" x-bind:key="option"><button type="button" class="focus-ring block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-200 hover:bg-white/10" x-on:click="endpoint.values[parameter.name] = option; open = false" x-text="option"></button></template></span></span></template>
                                            <template x-if="parameter.inputType === 'boolean'">
                                                <span class="mt-2 flex min-h-[var(--control-height)] items-center gap-2 rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-3"><input type="checkbox" x-model="endpoint.values[parameter.name]" class="rounded border-slate-600 bg-slate-900 text-blue-500 focus:ring-blue-500"><span class="text-sm text-[color:var(--color-text-secondary)]">Enviar true</span></span>
                                            </template>
                                            <template x-if="parameter.inputType === 'text' || parameter.inputType === 'number'">
                                                <input x-bind:type="parameter.inputType" x-model="endpoint.values[parameter.name]" x-bind:required="parameter.required" x-bind:min="parameter.schema.minimum" x-bind:max="parameter.schema.maximum" class="mt-1.5 min-h-[var(--control-height)] w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-3 text-sm text-white focus-ring">
                                            </template>
                                            <small x-show="parameter.description" class="mt-1 block text-xs text-[color:var(--color-text-muted)]" x-text="parameter.description"></small>
                                        </label>
                                    </template>
                                </div>
                                <p x-show="!endpoint.parameters.length" class="text-sm text-[color:var(--color-text-muted)]">Este endpoint no requiere parámetros.</p>

                                <div x-show="endpoint.response" class="mt-5 space-y-3" aria-live="polite">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" x-bind:class="endpoint.response?.ok ? 'bg-emerald-500/10 text-emerald-300' : 'bg-rose-500/10 text-rose-200'" x-text="endpoint.response?.status || 'Error de red'"></span>
                                        <span class="text-xs text-[color:var(--color-text-muted)]" x-text="(endpoint.response?.duration ?? 0) + ' ms · ' + (endpoint.response?.size ?? 0) + ' bytes'"></span>
                                        <span class="text-xs text-[color:var(--color-text-muted)]" x-text="endpoint.response?.headers?.etag ? 'ETag ' + endpoint.response.headers.etag : ''"></span>
                                    </div>
                                    <x-ui.alert tone="danger" x-show="endpoint.response?.error"><span x-text="endpoint.response?.error"></span></x-ui.alert>
                                    <div class="flex flex-wrap gap-1" role="tablist" aria-label="Formato de respuesta">
                                        <template x-for="tab in [{id:'body',label:'Respuesta'},{id:'headers',label:'Headers'},{id:'curl',label:'cURL'},{id:'fetch',label:'JavaScript'},{id:'schema',label:'Esquema'}]" x-bind:key="tab.id">
                                            <button type="button" class="focus-ring rounded-lg px-3 py-2 text-xs" x-bind:class="endpoint.responseTab === tab.id ? 'bg-white/10 text-white' : 'text-[color:var(--color-text-secondary)]'" x-on:click="endpoint.responseTab = tab.id" x-text="tab.label"></button>
                                        </template>
                                    </div>
                                    <div class="relative rounded-xl border border-[color:var(--color-border)] bg-[color:var(--color-background)] p-3">
                                        <button type="button" class="focus-ring absolute right-2 top-2 rounded-lg bg-white/10 px-2 py-1 text-xs text-white" x-show="endpoint.responseTab !== 'schema'" x-on:click="copy(endpoint.responseTab === 'body' ? endpoint.response?.bodyText : endpoint.responseTab === 'headers' ? endpoint.response?.headersText : endpoint.responseTab === 'curl' ? endpoint.response?.curl : endpoint.response?.fetch, endpoint.responseTab)">Copiar</button>
                                        <pre x-show="endpoint.responseTab === 'body'" class="max-h-96 overflow-auto whitespace-pre-wrap break-words pr-16 text-xs text-slate-200" x-text="endpoint.response?.bodyText"></pre>
                                        <pre x-show="endpoint.responseTab === 'headers'" class="max-h-96 overflow-auto whitespace-pre-wrap break-words pr-16 text-xs text-slate-200" x-text="endpoint.response?.headersText"></pre>
                                        <pre x-show="endpoint.responseTab === 'curl'" class="max-h-96 overflow-auto whitespace-pre-wrap break-words pr-16 text-xs text-slate-200" x-text="endpoint.response?.curl"></pre>
                                        <pre x-show="endpoint.responseTab === 'fetch'" class="max-h-96 overflow-auto whitespace-pre-wrap break-words pr-16 text-xs text-slate-200" x-text="endpoint.response?.fetch"></pre>
                                        <p x-show="endpoint.responseTab === 'schema'" class="text-sm text-[color:var(--color-text-secondary)]">Consulta el esquema completo en la pestaña “Esquemas” o en OpenAPI avanzada.</p>
                                    </div>
                                    <div x-show="endpoint.id === 'agencyChanges' && endpoint.response?.body?.meta?.next_cursor" class="flex flex-wrap gap-2">
                                        <x-ui.button type="button" size="sm" variant="secondary" x-on:click="copy(endpoint.response.body.meta.next_cursor, 'Cursor')">Copiar next_cursor</x-ui.button>
                                        <x-ui.button type="button" size="sm" variant="outline" x-on:click="useNextCursor(endpoint)">Usar next_cursor</x-ui.button>
                                    </div>
                                    <div x-show="endpoint.id === 'listAgencies' && Array.isArray(endpoint.response?.body?.data)" class="overflow-x-auto rounded-xl border border-[color:var(--color-border)]">
                                        <table class="min-w-full text-left text-xs"><thead class="bg-white/5 text-[color:var(--color-text-muted)]"><tr><th class="p-3">Code</th><th class="p-3">Agencia</th><th class="p-3">Ubicación</th><th class="p-3">Canales</th></tr></thead><tbody class="divide-y divide-white/5"><template x-for="agency in endpoint.response?.body?.data ?? []" x-bind:key="agency.internal_id"><tr><td class="p-3 font-mono text-blue-200" x-text="agency.code"></td><td class="p-3 text-white" x-text="agency.agencia"></td><td class="p-3 text-slate-300" x-text="[agency.departamento, agency.provincia, agency.distrito].filter(Boolean).join(' / ')"></td><td class="p-3 text-slate-300" x-text="[agency.texto_chosen_terrestre ? 'Terrestre' : null, agency.texto_chosen_aereo ? 'Aéreo' : null].filter(Boolean).join(', ') || 'Ninguno'"></td></tr></template></tbody></table>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </section>
        </template>

        <x-ui.empty-state x-show="!filteredEndpoints.length" title="No se encontraron endpoints" description="Prueba con otra búsqueda o categoría." />
    </div>

    <section x-show="activeTab === 'openapi'" aria-label="OpenAPI avanzada">
        <x-ui.card title="OpenAPI avanzada" description="Referencia Swagger completa para esquemas, Try it out y detalles de bajo nivel.">
            <p x-show="!swaggerReady" role="status" class="rounded-lg bg-white/5 p-4 text-sm text-[color:var(--color-text-secondary)]">Cargando referencia avanzada…</p>
            <div x-ref="swagger" id="codered-swagger-ui" class="codered-swagger-ui" aria-label="Referencia OpenAPI avanzada"></div>
        </x-ui.card>
    </section>

    <section x-show="activeTab === 'schemas'" aria-label="Esquemas OpenAPI">
        <x-ui.card title="Esquemas OpenAPI" description="Modelos que forman el contrato público de CodeRED Platform.">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <template x-for="(schema, name) in spec?.components?.schemas ?? {}" x-bind:key="name">
                    <article class="rounded-xl border border-[color:var(--color-border)] bg-white/5 p-4"><h3 class="font-mono text-sm font-semibold text-blue-200" x-text="name"></h3><p class="mt-2 text-xs text-[color:var(--color-text-secondary)]" x-text="schema.description || (schema.type === 'object' ? Object.keys(schema.properties ?? {}).length + ' propiedades' : schema.type)"></p></article>
                </template>
            </div>
        </x-ui.card>
    </section>
</div>
