<div class="mx-auto max-w-2xl">
    <x-ui.page-header title="Cambiar contraseña" subtitle="Tu cuenta requiere una contraseña nueva antes de continuar." />

    <x-ui.card title="Seguridad de la cuenta" description="Usa una contraseña de al menos 12 caracteres, con mayúsculas, minúsculas y números.">
        <form wire:submit="updatePassword" class="space-y-[var(--space-section)]" novalidate>
            <x-ui.input
                id="current-password"
                wire:model.live="current_password"
                type="password"
                name="current_password"
                label="Contraseña actual"
                autocomplete="current-password"
                required
                :error="$errors->first('current_password')"
            />
            <x-ui.input
                id="new-password"
                wire:model.live="password"
                type="password"
                name="password"
                label="Nueva contraseña"
                autocomplete="new-password"
                required
                :error="$errors->first('password')"
            />
            <x-ui.input
                id="password-confirmation"
                wire:model.live="password_confirmation"
                type="password"
                name="password_confirmation"
                label="Confirmar nueva contraseña"
                autocomplete="new-password"
                required
                :error="$errors->first('password_confirmation')"
            />
            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary" loading-target="updatePassword" loading-label="Actualizando…" wire:loading.attr="disabled" wire:target="updatePassword">
                    Actualizar contraseña
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
