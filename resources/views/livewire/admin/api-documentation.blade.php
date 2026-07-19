<div class="space-y-8" x-data="codeRedSwaggerDocs({ specUrl: @js(route('api.docs.spec')) })">
    <x-ui.page-header title="API CodeRED v1" subtitle="Referencia OpenAPI interactiva para integraciones de consulta.">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.api-tokens.index') }}" variant="secondary">Administrar tokens</x-ui.button>
            <x-ui.button href="{{ route('api.docs.spec') }}" variant="outline">Descargar OpenAPI</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" title="Autenticación Bearer con Sanctum">
        Genera un token en “Administrar tokens”, pulsa <strong>Authorize</strong> y pega el token completo. Swagger UI añade automáticamente el prefijo Bearer; también se normaliza si lo incluyes. La autorización permanece solo en memoria y se elimina al recargar.
    </x-ui.alert>

    <x-ui.card title="Referencia interactiva" description="Abre un endpoint, pulsa Try it out, completa parámetros y ejecuta una petición real. La respuesta incluye estado, cabeceras, cuerpo, duración y cURL.">
        <div class="mb-4 grid gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-lg bg-white/5 p-3"><span class="block text-[color:var(--color-text-muted)]">Límite</span><strong>{{ $rateLimit }}/minuto/token</strong></div>
            <div class="rounded-lg bg-white/5 p-3"><span class="block text-[color:var(--color-text-muted)]">Página máxima</span><strong>{{ $maxPerPage }} resultados</strong></div>
            <div class="rounded-lg bg-white/5 p-3"><span class="block text-[color:var(--color-text-muted)]">Contrato</span><strong>OpenAPI 3.0.3 · v1</strong></div>
        </div>
        <p x-show="! ready" role="status" class="rounded-lg bg-white/5 p-4 text-sm text-[color:var(--color-text-secondary)]">Cargando documentación interactiva…</p>
        <div x-ref="swagger" id="codered-swagger-ui" class="codered-swagger-ui" aria-label="Documentación interactiva de la API CodeRED"></div>
    </x-ui.card>

    <x-ui.card title="Uso del token" description="El secreto solo se muestra una vez al crearlo.">
        <ol class="grid gap-4 text-sm md:grid-cols-4">
            <li class="rounded-lg bg-white/5 p-4"><strong class="block text-white">1. Crear</strong>Genera un token con <code>agencies:read</code> y/o <code>profile:read</code>.</li>
            <li class="rounded-lg bg-white/5 p-4"><strong class="block text-white">2. Autorizar</strong>Pulsa <strong>Authorize</strong> y pega el valor sin guardarlo en el navegador.</li>
            <li class="rounded-lg bg-white/5 p-4"><strong class="block text-white">3. Ejecutar</strong>Usa <strong>Try it out</strong> y luego <strong>Execute</strong>.</li>
            <li class="rounded-lg bg-white/5 p-4"><strong class="block text-white">4. Revocar</strong>Revoca o rota la credencial desde “API y Tokens”.</li>
        </ol>
    </x-ui.card>
</div>
