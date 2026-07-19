<div class="space-y-8">
    <x-ui.page-header title="Usuarios" subtitle="Administración de cuentas, accesos y roles.">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.users.create') }}" variant="primary">Nuevo usuario</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat-card label="Total" :value="$users->total()" tone="brand" />
        <x-ui.stat-card label="En papelera" :value="$trashCount" tone="danger" />
    </div>

    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="Nombre o correo..." />
            <x-ui.dropdown-select id="users-status-filter" wire:model.live="status" label="Estado" :value="$status" :options="['' => 'Todos', 'active' => 'Activo', 'suspended' => 'Suspendido', 'inactive' => 'Inactivo']" icon-set="user-status" />
            <x-ui.dropdown-select id="users-role-filter" wire:model.live="role" label="Rol" :value="$role" :options="['' => 'Todos'] + $roles->pluck('name', 'slug')->all()" />
            <x-ui.dropdown-select id="users-verified-filter" wire:model.live="verified" label="Correo verificado" :value="$verified" :options="['' => 'Todos', '1' => 'Sí', '0' => 'No']" />
            <x-ui.dropdown-select id="users-access-filter" wire:model.live="access" label="Último acceso" :value="$access" :options="['' => 'Todos', '1' => 'Con acceso', '0' => 'Nunca']" />
            <x-ui.dropdown-select id="users-trash-filter" wire:model.live="trash" label="Registros" :value="$trash" :options="['' => 'Activos', 'only' => 'Papelera', 'with' => 'Todos']" />
            <x-ui.dropdown-select id="users-per-page" wire:model.live="perPage" label="Por página" :value="$perPage" :options="[15 => '15', 30 => '30', 50 => '50']" />
        </div>
    </x-ui.card>

    <div wire:loading.delay wire:target="search,status,role,verified,access,trash,perPage">
        <x-ui.skeleton variant="table" :rows="5" />
    </div>

    <x-ui.table id="users-list" wire:loading.class="opacity-50" wire:target="search,status,role,verified,access,trash,perPage">
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
                <tr @class(['transition hover:bg-white/5', 'opacity-70' => $user->trashed()])>
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
                            @if ($user->trashed())
                                <x-ui.badge tone="danger">En papelera</x-ui.badge>
                                @if ($canRestoreUsers)
                                    <x-ui.confirm-dialog id="restore-user-{{ $user->id }}" title="Restaurar usuario" message="La cuenta volverá a estar disponible para administración." confirm-label="Restaurar" confirm-action="restoreUser({{ $user->id }})" tone="primary">
                                        <x-slot:trigger><x-ui.button size="sm" variant="primary">Restaurar</x-ui.button></x-slot:trigger>
                                    </x-ui.confirm-dialog>
                                @endif
                                @if ($canForceDeleteUsers)
                                    <x-ui.confirm-dialog id="force-delete-user-{{ $user->id }}" title="Eliminar usuario definitivamente" message="Esta acción es irreversible y eliminará permanentemente la cuenta." confirm-label="Eliminar definitivamente" confirm-action="forceDeleteUser({{ $user->id }})">
                                        <x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar definitivamente</x-ui.button></x-slot:trigger>
                                    </x-ui.confirm-dialog>
                                @endif
                            @else
                                <x-ui.button href="{{ route('admin.users.show', $user) }}" size="sm" variant="outline">Ver</x-ui.button>
                                <x-ui.button href="{{ route('admin.users.edit', $user) }}" size="sm" variant="secondary">Editar</x-ui.button>
                                @if ($canDeleteUsers && ! auth()->user()->is($user))
                                    <x-ui.confirm-dialog id="delete-user-{{ $user->id }}" title="Mover usuario a la papelera" message="La cuenta dejará de estar disponible y podrá restaurarse más adelante." confirm-label="Mover a papelera" confirm-action="deleteUser({{ $user->id }})">
                                        <x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar</x-ui.button></x-slot:trigger>
                                    </x-ui.confirm-dialog>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12"><x-ui.empty-state title="No hay usuarios" description="Crea el primer usuario administrativo." icon="◌" /></td></tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <x-ui.pagination :paginator="$users" scroll-to="#users-list" />
</div>
