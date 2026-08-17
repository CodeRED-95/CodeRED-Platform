<div class="space-y-8">
    <x-ui.page-header title="Tokens API" subtitle="Clientes y credenciales separadas para Agencias, DNI, RUC y Shalom Recordar.">
        <x-slot:actions>
            <x-ui.button href="{{ route('api.docs') }}" variant="secondary">Documentación API</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($plainTextToken)
        @include('livewire.admin.api-tokens.partials.plain-token-alert')
    @endif

    <div class="flex flex-wrap gap-3">
        <x-ui.button
            type="button"
            variant="{{ $activeTab === 'tokens' ? 'primary' : 'secondary' }}"
            wire:click="showTokensTab"
            aria-pressed="{{ $activeTab === 'tokens' ? 'true' : 'false' }}"
        >
            Tokens emitidos
        </x-ui.button>
        <x-ui.button
            type="button"
            variant="{{ $activeTab === 'create' ? 'primary' : 'secondary' }}"
            wire:click="showCreateTab"
            aria-pressed="{{ $activeTab === 'create' ? 'true' : 'false' }}"
        >
            Crear token
        </x-ui.button>
    </div>

    @if ($activeTab === 'tokens')
        @include('livewire.admin.api-tokens.partials.tokens-list')
        @include('livewire.admin.api-tokens.partials.revoked-tokens')
        @include('livewire.admin.api-tokens.partials.request-logs')
    @else
        @include('livewire.admin.api-tokens.partials.create-client')
        @include('livewire.admin.api-tokens.partials.create-token-form')
    @endif
</div>
