<div>
    <x-ui.card>
        <x-ui.section-header title="Restablecer contraseña" subtitle="Genera una contraseña temporal segura." />
        <div class="mt-4 flex items-center gap-3">
            <x-ui.button type="button" variant="primary" wire:click="resetPassword">Restablecer</x-ui.button>
            <span class="text-sm text-[color:var(--color-text-secondary)]">La contraseña se genera y no se vuelve a mostrar.</span>
        </div>
    </x-ui.card>
</div>
