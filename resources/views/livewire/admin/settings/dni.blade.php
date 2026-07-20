<div class="space-y-6">
    <x-ui.page-header title="API DNI / PeruDevs" subtitle="La base interna es la fuente principal; PeruDevs opera únicamente como respaldo." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Estado del proveedor">
            <div class="space-y-2 text-sm">
                <p>Proveedor: <strong>PeruDevs</strong></p>
                <p>Estado: <x-ui.badge :tone="$apiKeyConfigured && $enabled ? 'success' : 'warning'">{{ $apiKeyConfigured ? 'Configurado' : 'Sin configurar' }}</x-ui.badge></p>
                <p>Servicio: {{ $enabled ? 'Activo' : 'Inactivo' }}</p>
                <p>API key configurada: {{ $apiKeyConfigured ? 'Sí' : 'No' }}</p>
                <p class="text-[color:var(--color-text-muted)]">{{ $apiKeyMasked ?? 'Sin API key almacenada' }}</p>
            </div>
        </x-ui.card>

        <x-ui.card title="Prueba de conexión" description="La prueba no persiste ni audita el DNI como consulta real.">
            <div class="space-y-3">
                <x-ui.input id="test-dni" wire:model="testDni" label="DNI de prueba" inputmode="numeric" maxlength="8" :error="$errors->first('testDni')" />
                <x-ui.button wire:click="testConnection" loading-target="testConnection">Probar conexión</x-ui.button>
                @if($testResult)
                    <x-ui.alert :tone="$testResult['ok'] ? 'success' : 'danger'">Resultado: {{ $testResult['status'] }} · HTTP {{ $testResult['status_code'] ?? 'N/D' }} · {{ $testResult['response_time_ms'] }} ms</x-ui.alert>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card title="Acciones sensibles">
            <div class="space-y-3">
                <x-ui.confirm-dialog id="clear-dni-cache" title="Limpiar caché DNI" message="Las siguientes consultas no locales volverán a evaluar el proveedor." confirm-label="Limpiar caché" confirm-action="clearCache">
                    <x-slot:trigger><x-ui.button variant="secondary">Limpiar caché DNI</x-ui.button></x-slot:trigger>
                </x-ui.confirm-dialog>
                <x-ui.confirm-dialog id="delete-perudevs-key" title="Eliminar API key PeruDevs" message="El proveedor quedará sin credenciales hasta configurar una API key nueva." confirm-label="Eliminar API key" confirm-action="deleteApiKey">
                    <x-slot:trigger><x-ui.button variant="danger">Eliminar API key</x-ui.button></x-slot:trigger>
                </x-ui.confirm-dialog>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card title="Configuración" description="Los valores guardados aquí tienen prioridad sobre .env.">
        <form wire:submit="save" class="grid gap-5 md:grid-cols-2">
            <x-ui.toggle wire:model="enabled" label="Proveedor activo" description="Consulta PeruDevs solo cuando el DNI no existe localmente ni en caché negativa." />
            <x-ui.toggle wire:model="persistResults" label="Guardar resultados externos" description="La siguiente consulta se resolverá desde PostgreSQL." />
            <x-ui.input id="perudevs-url" wire:model="baseUrl" label="URL base de PeruDevs" required :error="$errors->first('baseUrl')" />
            <x-ui.input id="perudevs-key" wire:model="newApiKey" type="password" autocomplete="new-password" label="Nueva API key" placeholder="Vacío conserva la API key actual" :error="$errors->first('newApiKey')" />
            <x-ui.input id="dni-timeout" wire:model="timeoutSeconds" type="number" min="1" max="60" label="Timeout (segundos)" />
            <x-ui.input id="dni-retries" wire:model="retryTimes" type="number" min="0" max="5" label="Reintentos" />
            <x-ui.input id="dni-cache-ttl" wire:model="cacheTtlSeconds" type="number" min="60" label="Caché exitoso (segundos)" />
            <x-ui.input id="dni-not-found-ttl" wire:model="notFoundCacheTtlSeconds" type="number" min="30" label="Caché no encontrado (segundos)" />
            <x-ui.input id="dni-refresh-days" wire:model="refreshAfterDays" type="number" min="1" max="365" label="Volver a verificar después de (días)" />
            <div class="flex items-end"><x-ui.button type="submit" class="w-full" loading-target="save">Guardar configuración</x-ui.button></div>
        </form>
    </x-ui.card>
</div>
