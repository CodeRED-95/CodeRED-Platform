<x-ui.card title="Crear token" description="Selecciona uno o varios permisos. El secreto se mostrará una sola vez.">
    <form wire:submit="createToken" class="space-y-4">
        <x-ui.input id="token-name" wire:model="name" label="Nombre" required :error="$errors->first('name')" placeholder="Extensión Chrome - PC principal" />
        <x-ui.textarea id="token-description" wire:model="description" label="Descripción" :error="$errors->first('description')" />
        <x-ui.dropdown-select id="token-client-create" wire:model="targetApiClientId" label="Propietario o cliente" :value="$targetApiClientId" :options="[0 => 'Usuario administrador (compatibilidad)'] + $clients->pluck('name', 'id')->all()" :error="$errors->first('targetApiClientId')" />
        <div class="space-y-2">
            <x-ui.input wire:model.live="tokenExpiresInDays" type="number" min="1" max="365" step="1" label="Vigencia del token en días" :error="$errors->first('tokenExpiresInDays')" />
            <div class="flex flex-wrap gap-2">
                @foreach ($tokenExpirationQuickOptions as $days)
                    <x-ui.button type="button" size="sm" variant="ghost" wire:click="setTokenExpiresInDays({{ $days }})">{{ $days }} {{ $days === 1 ? 'día' : 'días' }}</x-ui.button>
                @endforeach
            </div>
            <p class="text-xs text-[color:var(--color-text-muted)]">{{ $tokenExpirationPreview }}</p>
        </div>
        <x-ui.token-abilities-selector
            :abilities="$abilities"
            :available-abilities="$availableAbilities"
            :allowed-abilities="$allowedAbilities"
            :selected-abilities="$selectedAbilities"
            :error="$errors->first('abilities')"
        />
        <x-ui.button type="submit" variant="primary" class="w-full" loading-target="createToken">Generar token</x-ui.button>
    </form>
</x-ui.card>
