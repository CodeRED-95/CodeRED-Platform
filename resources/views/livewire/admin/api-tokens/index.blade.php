<div class="space-y-8">
    <x-ui.page-header title="Tokens API" subtitle="Clientes y credenciales separadas para Agencias, DNI, RUC y Shalom Recordar.">
        <x-slot:actions>
            <x-ui.button href="{{ route('api.docs') }}" variant="secondary">Documentación API</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($plainTextToken)
        @include('livewire.admin.api-tokens.partials.plain-token-alert')
    @endif

    <x-ui.card title="Nuevo cliente API" description="Identidad independiente de los usuarios del panel web.">
        <form wire:submit="createClient" class="grid gap-4 md:grid-cols-4">
            <x-ui.input id="client-name" wire:model="clientName" label="Nombre del cliente" required :error="$errors->first('clientName')" />
            <x-ui.input id="client-contact" wire:model="clientContactName" label="Contacto" :error="$errors->first('clientContactName')" />
            <x-ui.input id="client-email" wire:model="clientContactEmail" type="email" label="Correo de contacto" :error="$errors->first('clientContactEmail')" />
            <div class="flex items-end"><x-ui.button type="submit" class="w-full" loading-target="createClient">Crear cliente</x-ui.button></div>
        </form>
        <div class="mt-4 flex gap-2 text-sm text-[color:var(--color-text-secondary)]">
            <span>Agencias: {{ $usageSummary['agencias'] ?? 0 }}</span><span>·</span><span>DNI: {{ $usageSummary['dni'] ?? 0 }}</span>
        </div>
    </x-ui.card>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            @include('livewire.admin.api-tokens.partials.tokens-list')
            @include('livewire.admin.api-tokens.partials.revoked-tokens')
        </div>

        @include('livewire.admin.api-tokens.partials.create-token-form')
    </div>

    @include('livewire.admin.api-tokens.partials.request-logs')
</div>
