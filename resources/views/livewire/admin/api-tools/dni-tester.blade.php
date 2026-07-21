<div class="space-y-6">
    <x-ui.page-header title="Probar API DNI" subtitle="Ejecuta el mismo flujo de producción sin exponer credenciales." />

    <x-ui.card title="Consulta">
        <form wire:submit="consult" class="grid gap-5 lg:grid-cols-3">
            <x-ui.input id="tester-dni" wire:model="dni" label="Número de DNI" inputmode="numeric" pattern="[0-9]{8}" minlength="8" maxlength="8" required :error="$errors->first('dni')" />
            <div>
                <span class="mb-2 block text-sm font-medium">Modo de prueba</span>
                <div class="space-y-2">
                    <x-ui.radio wire:model.live="mode" value="internal" label="Prueba mediante servicio interno" description="Valida base interna, caché y proveedor." />
                    <x-ui.radio wire:model.live="mode" value="endpoint" label="Prueba mediante endpoint API" description="Valida Sanctum, abilities y rate limit." />
                </div>
            </div>
            @if($mode === 'endpoint')
                <x-ui.dropdown-select
                    id="tester-token"
                    wire:model="tokenId"
                    label="Token de referencia"
                    :value="$tokenId"
                    :options="[0 => 'Selecciona un token'] + $tokens->mapWithKeys(fn ($token) => [$token->id => $token->name.' · '.implode(', ', $token->abilities ?? [])])->all()"
                    :error="$errors->first('tokenId')"
                />
            @endif
            <div class="flex items-end gap-3 lg:col-span-3">
                <x-ui.button type="submit" loading-target="consult">Consultar</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="clear">Limpiar</x-ui.button>
                <span wire:loading wire:target="consult" role="status">Procesando consulta…</span>
            </div>
        </form>
    </x-ui.card>

    @if($errorMessage)
        <x-ui.alert tone="danger">{{ $errorMessage }}</x-ui.alert>
    @endif

    @if($result)
        <x-ui.card title="Resultado" description="Formato público de CodeRED Platform">
            <div class="mb-5 flex flex-wrap gap-3">
                <x-ui.badge tone="success">Consulta exitosa</x-ui.badge>
                <x-ui.badge>{{ match($technical['source']) { 'internal' => 'Base de datos interna', 'cache' => 'Caché', 'perudevs' => 'PeruDevs', default => 'No determinado' } }}</x-ui.badge>
                <span class="text-sm">{{ $technical['response_time_ms'] }} ms · HTTP {{ $technical['http_status'] }}</span>
            </div>
            <dl class="grid gap-4 md:grid-cols-2">
                @foreach(['dni' => 'DNI', 'nombres' => 'Nombres', 'apellido_paterno' => 'Apellido paterno', 'apellido_materno' => 'Apellido materno', 'nombre_completo' => 'Nombre completo', 'genero' => 'Género', 'fecha_nacimiento' => 'Fecha de nacimiento', 'edad' => 'Edad', 'codigo_verificacion' => 'Código de verificación'] as $key => $label)
                    <div class="rounded-[var(--radius-control)] bg-white/5 p-4">
                        <dt class="text-xs text-[color:var(--color-text-muted)]">{{ $label }}</dt>
                        <dd class="mt-1 break-words">{{ $result[$key] ?? '—' }}</dd>
                        <x-ui.button type="button" variant="ghost" class="mt-2" x-on:click="$dispatch('codered-copy', { value: @js((string) ($result[$key] ?? '')) })">Copiar {{ strtolower($label) }}</x-ui.button>
                    </div>
                @endforeach
            </dl>
            <x-ui.button type="button" variant="secondary" class="mt-5" x-on:click="$dispatch('codered-copy', { value: @js($copyJson) })">Copiar respuesta JSON</x-ui.button>
        </x-ui.card>
    @endif

    @if($technical)
        <x-ui.card title="Detalles técnicos">
            <details>
                <summary class="cursor-pointer font-medium">Mostrar detalles seguros</summary>
                <dl class="mt-4 grid gap-3 md:grid-cols-2">
                    <div>Código HTTP: {{ $technical['http_status'] }}</div>
                    <div>Tiempo total: {{ $technical['response_time_ms'] ?? 'N/D' }} ms</div>
                    <div>Origen: {{ $technical['source'] }}</div>
                    <div>Base de datos consultada: {{ $technical['local_database_hit'] ? 'Sí' : 'No' }}</div>
                    <div>Caché utilizada: {{ $technical['cache_hit'] ? 'Sí' : 'No' }}</div>
                    <div>PeruDevs consultado: {{ $technical['provider_called'] ? 'Sí' : 'No' }}</div>
                    <div>Registro persistido: {{ $technical['persisted'] ? 'Sí' : 'No' }}</div>
                    <div>Token utilizado: {{ $technical['token_name'] ?? 'No aplica' }}</div>
                    <div>Ability verificada: {{ $technical['ability_verified'] ? 'dni:consultar' : 'No aplica' }}</div>
                    <div>Fecha y hora: {{ $technical['tested_at'] }}</div>
                </dl>
            </details>
        </x-ui.card>
    @endif
</div>
