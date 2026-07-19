<div class="mx-auto max-w-4xl space-y-6">
    <x-ui.page-header title="Mi perfil" subtitle="Administra tu información personal y la seguridad de tu cuenta." />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Información personal" description="Estos datos identifican tu cuenta dentro de CodeRED Platform.">
            <form wire:submit="updateProfile" class="space-y-5" novalidate>
                <x-ui.input id="profile-name" wire:model.live="name" name="name" label="Nombre" autocomplete="name" required :error="$errors->first('name')" />
                <x-ui.input id="profile-email" wire:model.live="email" name="email" type="email" label="Correo electrónico" autocomplete="email" required :error="$errors->first('email')" />
                <p class="text-xs text-[color:var(--color-text-secondary)]">Si cambias el correo, su verificación se reiniciará. El proyecto no envía todavía un nuevo mensaje de verificación.</p>
                <div class="flex justify-end">
                    <x-ui.button type="submit" loading-target="updateProfile" loading-label="Guardando…" wire:loading.attr="disabled" wire:target="updateProfile">Guardar cambios</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Seguridad" description="Usa al menos 12 caracteres, con mayúsculas, minúsculas y números.">
            <form wire:submit="updatePassword" class="space-y-5" novalidate>
                <x-ui.input id="profile-current-password" wire:model.live="current_password" name="current_password" type="password" label="Contraseña actual" autocomplete="current-password" required :error="$errors->first('current_password')" />
                <x-ui.input id="profile-password" wire:model.live="password" name="password" type="password" label="Nueva contraseña" autocomplete="new-password" required :error="$errors->first('password')" />
                <x-ui.input id="profile-password-confirmation" wire:model.live="password_confirmation" name="password_confirmation" type="password" label="Confirmar nueva contraseña" autocomplete="new-password" required :error="$errors->first('password_confirmation')" />
                <div class="flex justify-end">
                    <x-ui.button type="submit" loading-target="updatePassword" loading-label="Actualizando…" wire:loading.attr="disabled" wire:target="updatePassword">Actualizar contraseña</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    <x-ui.card title="Información de cuenta" description="Estos valores son administrados por un superadministrador y no pueden modificarse desde tu perfil.">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-xs uppercase tracking-wider text-[color:var(--color-text-muted)]">Estado</dt><dd class="mt-1 font-medium">{{ auth()->user()->status === 'active' ? 'Activo' : ucfirst(auth()->user()->status) }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-[color:var(--color-text-muted)]">Roles</dt><dd class="mt-1 font-medium">{{ auth()->user()->roles->pluck('name')->join(', ') ?: 'Sin rol asignado' }}</dd></div>
        </dl>
    </x-ui.card>
</div>
