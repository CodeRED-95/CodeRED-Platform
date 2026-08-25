<x-ui.card title="Tokens emitidos" description="El valor completo nunca se almacena ni vuelve a mostrarse; los permisos se asignan por tipo.">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="Nombre o propietario" />
        <x-ui.dropdown-select id="token-status" wire:model.live="status" label="Estado" :value="$status" :options="['' => 'Todos', 'active' => 'Activos', 'expiring' => 'Próximos a expirar', 'expired' => 'Expirados']" />
        <x-ui.dropdown-select id="token-ability" wire:model.live="ability" label="Ability" :value="$ability" :options="['' => 'Todas'] + $abilityFilterOptions" />
        <x-ui.dropdown-select id="token-owner" wire:model.live="ownerId" label="Propietario" :value="$ownerId" :options="[0 => 'Todos'] + $users->pluck('name', 'id')->all()" />
        <x-ui.input id="token-created-from" wire:model.live="createdFrom" type="date" label="Creado desde" />
        <x-ui.input id="token-created-to" wire:model.live="createdTo" type="date" label="Creado hasta" />
    </div>

    @if ($selectedTokenIds !== [])
        <x-ui.card class="mt-4 border-[color:var(--color-danger)]/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-medium">{{ count($selectedTokenIds) }} tokens seleccionados</p>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="ghost" size="sm" wire:click="clearSelection">Limpiar selección</x-ui.button>
                    <x-ui.confirm-dialog id="bulk-revoke-api-tokens" title="Revocar tokens seleccionados" message="Los clientes que utilicen estos tokens perderán acceso inmediatamente." confirm-label="Revocar tokens" confirm-action="revokeSelected">
                        <x-slot:trigger><x-ui.button variant="danger" size="sm">Revocar seleccionados</x-ui.button></x-slot:trigger>
                    </x-ui.confirm-dialog>
                </div>
            </div>
        </x-ui.card>
    @endif

    <x-ui.table id="api-token-list" class="mt-4">
        <thead class="bg-white/5 text-xs uppercase tracking-[0.16em] text-[color:var(--color-text-secondary)]">
            <tr>
                <th class="px-4 py-4"><x-ui.checkbox aria-label="Seleccionar tokens visibles" wire:click="selectVisible(@js($tokens->pluck('id')->all()))" :checked="count($selectedTokenIds) > 0 && count($selectedTokenIds) === $tokens->count()" /></th>
                <th class="px-4 py-4">Token</th>
                <th class="px-4 py-4">Abilities</th>
                <th class="px-4 py-4">Uso y expiración</th>
                <th class="px-4 py-4">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse ($tokens as $token)
                @php
                    $revoked = $token->revoked_at !== null;
                    $expired = $token->expires_at?->isPast() ?? false;
                    $expiring = ! $expired && $token->expires_at?->lte(now()->addDays(7));
                @endphp
                <tr class="align-top transition hover:bg-white/5">
                    <td class="px-4 py-4"><x-ui.checkbox wire:model="selectedTokenIds" value="{{ $token->id }}" aria-label="Seleccionar token {{ $token->name }}" /></td>
                    <td class="px-4 py-4">
                        <p class="font-medium">{{ $token->name }}</p>
                        <p class="text-sm text-[color:var(--color-text-secondary)]">{{ $token->tokenable?->name ?? 'Propietario no disponible' }} · #{{ $token->id }}</p>
                        @if ($token->description)<p class="mt-1 max-w-md text-xs text-[color:var(--color-text-muted)]">{{ $token->description }}</p>@endif
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex max-w-sm flex-wrap gap-1">
                            @foreach ($token->abilities ?? [] as $tokenAbility)
                                <x-ui.badge tone="info">{{ $tokenAbility }}</x-ui.badge>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-4 text-sm">
                        <x-ui.badge :tone="$revoked || $expired ? 'danger' : ($expiring ? 'warning' : 'success')">{{ $revoked ? 'Revocado' : ($expired ? 'Expirado' : ($expiring ? 'Próximo a expirar' : 'Activo')) }}</x-ui.badge>
                        <p class="mt-2 text-[color:var(--color-text-secondary)]">Último uso: {{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Nunca utilizado' }}</p>
                        <p class="text-[color:var(--color-text-secondary)]">Consultas: {{ $token->request_logs_count }} · Agencias {{ $token->agency_requests_count }} · DNI {{ $token->dni_requests_count }} · RUC {{ $token->ruc_requests_count }}</p>
                        <p class="text-[color:var(--color-text-secondary)]">Exitosas: {{ $token->successful_requests_count }} · Errores: {{ $token->failed_requests_count }}</p>
                        <p class="text-[color:var(--color-text-secondary)]">Expira: {{ $token->expires_at?->format('d/m/Y H:i') ?? 'Sin expiración' }}</p>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button size="sm" variant="secondary" wire:click="rotateToken({{ $token->id }})" wire:loading.attr="disabled">Rotar</x-ui.button>
                            @php($tieneControlHorario = in_array('extension:blocking', (array) ($token->abilities ?? []), true))
                            <x-ui.button
                                size="sm"
                                :variant="$tieneControlHorario ? 'info' : 'ghost'"
                                wire:click="toggleBlockingAbility({{ $token->id }})"
                                wire:loading.attr="disabled"
                                title="{{ $tieneControlHorario ? 'Este token aplica el control horario configurado en el panel' : 'Este token no aplica ningún control horario' }}">
                                {{ $tieneControlHorario ? 'Control horario: sí' : 'Control horario: no' }}
                            </x-ui.button>
                            <x-ui.confirm-dialog id="revoke-token-{{ $token->id }}" title="Revocar token" message="{{ $token->name }} dejará de funcionar inmediatamente. Último uso: {{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Nunca utilizado' }}." confirm-label="Revocar token" confirm-action="revokeToken({{ $token->id }})">
                                <x-slot:trigger><x-ui.button size="sm" variant="danger">Revocar</x-ui.button></x-slot:trigger>
                            </x-ui.confirm-dialog>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-12"><x-ui.empty-state title="No hay tokens" description="Crea una credencial con las abilities mínimas necesarias." icon="◈" /></td></tr>
            @endforelse
        </tbody>
    </x-ui.table>
    <x-ui.pagination :paginator="$tokens" scroll-to="#api-token-list" />
</x-ui.card>
