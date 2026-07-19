<div class="space-y-8">
    <x-ui.page-header :title="$user->name" subtitle="{{ $user->email }}">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.users.edit', $user) }}" variant="primary">Editar</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
        <x-ui.card>
            <x-ui.section-header title="Perfil" subtitle="Datos generales y estado." />
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div><p class="text-sm text-[color:var(--color-text-secondary)]">Estado</p><p class="font-medium">{{ ucfirst($user->status ?? 'active') }}</p></div>
                <div><p class="text-sm text-[color:var(--color-text-secondary)]">Último acceso</p><p class="font-medium">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</p></div>
                <div><p class="text-sm text-[color:var(--color-text-secondary)]">Correo verificado</p><p class="font-medium">{{ $user->email_verified_at ? 'Sí' : 'No' }}</p></div>
                <div><p class="text-sm text-[color:var(--color-text-secondary)]">Forzar cambio</p><p class="font-medium">{{ $user->must_change_password ? 'Sí' : 'No' }}</p></div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="Roles y permisos" subtitle="Acceso efectivo por rol." />
            <div class="mt-4 space-y-3">
                <div class="space-y-2">
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Roles asignados</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($user->roles as $role)
                            <x-ui.badge tone="brand">{{ $role->name }}</x-ui.badge>
                        @endforeach
                    </div>
                </div>
                <div class="space-y-2">
                    <p class="text-sm text-[color:var(--color-text-secondary)]">Permisos efectivos</p>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($effectivePermissions as $permission)
                            <x-ui.badge tone="neutral">{{ $permission }}</x-ui.badge>
                        @empty
                            <span class="text-sm text-[color:var(--color-text-secondary)]">Sin permisos registrados.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-ui.card>
    </div>

    @if ($canViewActivity)
        <x-ui.card>
            <x-ui.section-header title="Historial de auditoría" subtitle="Responsables, cambios y contexto de seguridad de la cuenta." />
            <div class="mt-4 space-y-3">
                @forelse ($activity as $item)
                    <x-ui.audit-entry :entry="$item" />
                @empty
                    <x-ui.empty-state title="Sin actividad" description="Todavía no hay eventos registrados para esta cuenta." icon="◌" />
                @endforelse
            </div>
        </x-ui.card>
    @endif
</div>
