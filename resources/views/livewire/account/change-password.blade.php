<div class="mx-auto max-w-2xl">
    <x-ui.page-header title="Cambiar contraseña" subtitle="Tu cuenta requiere una contraseña nueva antes de continuar." />

    <x-ui.card>
        <form wire:submit="updatePassword" class="space-y-4">
            <x-ui.input wire:model.live="current_password" type="password" label="Contraseña actual" autocomplete="current-password" />
            <x-ui.input wire:model.live="password" type="password" label="Nueva contraseña" autocomplete="new-password" />
            <x-ui.input wire:model.live="password_confirmation" type="password" label="Confirmar nueva contraseña" autocomplete="new-password" />
            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary">Actualizar contraseña</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
