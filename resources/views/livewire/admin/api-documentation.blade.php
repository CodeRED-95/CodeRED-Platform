<div class="space-y-8" x-data="codeRedApiDocs({ baseUrl: @js(url('/api/v1')) })">
    <x-ui.page-header title="API CodeRED v1" subtitle="Referencia interactiva para integraciones de consulta.">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.api-tokens.index') }}" variant="secondary">Administrar tokens</x-ui.button>
            <x-ui.button href="{{ route('api.docs.spec') }}" variant="outline">Descargar OpenAPI</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" title="Autenticación Bearer">
        Los endpoints privados requieren un token Sanctum. El valor escrito abajo permanece únicamente en memoria de esta pestaña y no se guarda en CodeRED Platform.
    </x-ui.alert>

    <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <div class="space-y-6">
            <x-ui.card title="Probar la API" description="Nunca pegues un token en una URL ni en capturas.">
                <div class="space-y-4">
                    <x-ui.input id="docs-api-token" x-model="token" type="password" label="Bearer token" autocomplete="off" placeholder="1|..." />
                    <x-ui.dropdown-select id="docs-api-endpoint" x-model="endpoint" label="Endpoint" value="agencies?per_page=5" :options="[
                        'health' => 'Health público',
                        'agencies?per_page=5' => 'Listar agencias',
                        'catalog/metadata' => 'Metadatos',
                        'me' => 'Propietario del token',
                    ]" />
                    <x-ui.button variant="primary" class="w-full" x-on:click="execute" x-bind:disabled="loading"><span x-text="loading ? 'Consultando…' : 'Ejecutar GET'">Ejecutar GET</span></x-ui.button>
                    <p class="text-sm text-[color:var(--color-text-secondary)]" aria-live="polite" x-text="status"></p>
                </div>
            </x-ui.card>

            <x-ui.card title="Límites">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt>Solicitudes</dt><dd>{{ $rateLimit }}/minuto/token</dd></div>
                    <div class="flex justify-between gap-4"><dt>Resultados máximos</dt><dd>{{ $maxPerPage }}/página</dd></div>
                    <div class="flex justify-between gap-4"><dt>Versión</dt><dd>v1</dd></div>
                </dl>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Respuesta" description="La cabecera Authorization nunca se muestra.">
                <pre class="max-h-[32rem] min-h-48 overflow-auto rounded-xl bg-black/30 p-4 text-xs leading-relaxed text-slate-200" tabindex="0"><code x-text="output">Selecciona un endpoint y ejecuta la consulta.</code></pre>
            </x-ui.card>

            <x-ui.card title="Endpoints oficiales">
                <div class="divide-y divide-white/5">
                    @foreach ([
                        ['GET', '/api/v1/health', 'Público', 'Estado básico de aplicación y base de datos.'],
                        ['GET', '/api/v1/agencies', 'agencies:read', 'Listado paginado con búsqueda y filtros.'],
                        ['GET', '/api/v1/agencies/{code}', 'agencies:read', 'Detalle inequívoco mediante Code.'],
                        ['GET', '/api/v1/catalog/metadata', 'agencies:read', 'Versión, totales, estados y canales.'],
                        ['GET', '/api/v1/me', 'profile:read', 'Propietario y abilities del token actual.'],
                    ] as $endpoint)
                        <article class="grid gap-2 py-4 md:grid-cols-[4rem_minmax(0,1fr)_10rem]">
                            <x-ui.badge tone="success">{{ $endpoint[0] }}</x-ui.badge>
                            <div><code class="text-sm text-white">{{ $endpoint[1] }}</code><p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">{{ $endpoint[3] }}</p></div>
                            <code class="text-xs text-[color:var(--color-brand-light)]">{{ $endpoint[2] }}</code>
                        </article>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card title="Ejemplo cURL">
                <pre class="overflow-auto rounded-xl bg-black/30 p-4 text-xs text-slate-200"><code>curl "{{ url('/api/v1/agencies?status=active&per_page=50') }}" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN"</code></pre>
            </x-ui.card>
        </div>
    </div>
</div>
