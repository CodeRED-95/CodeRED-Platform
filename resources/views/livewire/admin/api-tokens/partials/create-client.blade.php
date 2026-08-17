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
