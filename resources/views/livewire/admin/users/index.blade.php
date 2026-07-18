<div class="space-y-8">
    <x-ui.page-header title="Usuarios" subtitle="Administración de cuentas, accesos y roles.">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.users.create') }}" variant="primary">Nuevo usuario</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat-card label="Total" :value="$users->total()" tone="brand" />
    </div>

    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.input wire:model.live.debounce.400ms="search" type="search" label="Buscar" placeholder="Nombre o correo..." />
            <x-ui.select wire:model.live="status" label="Estado">
                <option value="">Todos</option>
                <option value="active">Activo</option>
                <option value="suspended">Suspendido</option>
                <option value="inactive">Inactivo</option>
            </x-ui.select>
            <x-ui.select wire:model.live="role" label="Rol">
                <option value="">Todos</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->slug }}">{{ $role->name }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="verified" label="Correo verificado">
                <option value="">Todos</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
            </x-ui.select>
            <x-ui.select wire:model.live="access" label="Último acceso">
                <option value="">Todos</option>
                <option value="1">Con acceso</option>
                <option value="0">Nunca</option>
            </x-ui.select>
            <x-ui.select wire:model.live="withTrashed" label="Eliminados">
                <option value="">No</option>
                <option value="1">Sí</option>
            </x-ui.select>
            <x-ui.select wire:model.live="perPage" label="Por página">
                <option value="15">15</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </x-ui.select>
        </div>
    </x-ui.card>

    <x-ui.table>
        <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-[color:var(--color-text-secondary)]">
            <tr>
                <th class="px-5 py-4">Usuario</th>
                <th class="px-5 py-4">Roles</th>
                <th class="px-5 py-4">Estado</th>
                <th class="px-5 py-4">Verificado</th>
                <th class="px-5 py-4">Último acceso</th>
                <th class="px-5 py-4">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse ($users as $user)
                <tr class="transition hover:bg-white/5">
                    <td class="px-5 py-4">
                        <div class="font-medium">{{ $user->name }}</div>
                        <div class="text-sm text-[color:var(--color-text-secondary)]">{{ $user->email }}</div>
                    </td>
                    <td class="px-5 py-4 text-sm text-[color:var(--color-text-secondary)]">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                    <td class="px-5 py-4"><x-ui.badge tone="{{ $user->status === 'active' ? 'success' : ($user->status === 'suspended' ? 'warning' : 'neutral') }}">{{ ucfirst($user->status ?? 'activo') }}</x-ui.badge></td>
                    <td class="px-5 py-4 text-sm">{{ $user->email_verified_at ? 'Sí' : 'No' }}</td>
                    <td class="px-5 py-4 text-sm">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button href="{{ route('admin.users.show', $user) }}" size="sm" variant="outline">Ver</x-ui.button>
                            <x-ui.button href="{{ route('admin.users.edit', $user) }}" size="sm" variant="secondary">Editar</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12"><x-ui.empty-state title="No hay usuarios" description="Crea el primer usuario administrativo." icon="◌" /></td></tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <x-ui.pagination :paginator="$users" />
</div>
