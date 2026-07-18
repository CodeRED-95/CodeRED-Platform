<div class="space-y-8">
    <x-ui.page-header :title="$mode === 'edit' ? 'Editar usuario' : 'Nuevo usuario'" subtitle="Gestiona cuentas, roles y estado.">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.users.index') }}" variant="secondary">Volver</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit.prevent="save" class="grid gap-6 lg:grid-cols-2">
        <x-ui.card class="lg:col-span-2">
            <x-ui.section-header title="Identificación" subtitle="Datos básicos de la cuenta." />
            @if ($errors->any())
                <x-ui.alert variant="danger" title="Revisa el formulario">
                    Se encontraron errores de validación. Corrige los campos marcados antes de guardar.
                </x-ui.alert>
            @endif
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-ui.input wire:model.live="name" label="Nombre" :error="$errors->first('name')" />
                <x-ui.input wire:model.live="email" type="email" label="Correo electrónico" :error="$errors->first('email')" />
                <x-ui.dropdown-select
                    id="user-status"
                    name="status"
                    wire:model.live="status"
                    label="Estado"
                    :value="$status"
                    :options="['active' => 'Activo', 'suspended' => 'Suspendido', 'inactive' => 'Inactivo']"
                    icon-set="user-status"
                    :error="$errors->first('status')"
                />
                <x-ui.toggle wire:model.live="email_verified" label="Correo verificado" />
                <x-ui.toggle wire:model.live="must_change_password" label="Forzar cambio de contraseña" />
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="Seguridad" subtitle="Contraseña temporal y confirmación." />
            <div class="mt-4 space-y-4">
                <x-ui.input wire:model.live="password" type="password" label="Contraseña temporal" autocomplete="new-password" :error="$errors->first('password')" />
                <x-ui.input wire:model.live="password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" :error="$errors->first('password_confirmation')" />
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="Roles" subtitle="Asignación por slug técnico." />
            <div class="mt-4 space-y-3">
                @foreach ($availableRoles as $role)
                    <label class="flex items-center gap-3 rounded-2xl border border-[color:var(--color-border)] bg-white/5 px-4 py-3">
                        <input type="checkbox" wire:model.live="roles" value="{{ $role->slug }}" class="rounded border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-brand)] focus-ring">
                        <span>
                            <span class="block font-medium">{{ $role->name }}</span>
                            <span class="block text-sm text-[color:var(--color-text-secondary)]">{{ $role->slug }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </x-ui.card>

        <div class="lg:col-span-2 flex justify-end">
            <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Guardar usuario</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </x-ui.button>
        </div>
    </form>
</div>
