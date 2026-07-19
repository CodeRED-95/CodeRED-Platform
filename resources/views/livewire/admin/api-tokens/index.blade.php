<div class="space-y-8">
    <x-ui.page-header title="API y Tokens" subtitle="Credenciales Sanctum para integraciones de solo lectura.">
        <x-slot:actions>
            <x-ui.button href="{{ route('api.docs') }}" variant="secondary">Documentación API</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($plainTextToken)
        <x-ui.alert tone="warning" title="Copia este token ahora. No podrá volver a mostrarse.">
            <div class="mt-3 space-y-3" x-data="codeRedTokenCopy(@js($plainTextToken))">
                <p class="text-sm">Token: <strong>{{ $createdTokenName }}</strong></p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <code
                        x-ref="tokenText"
                        tabindex="0"
                        class="min-w-0 flex-1 overflow-x-auto rounded-xl bg-black/30 px-4 py-3 text-sm text-white focus-ring"
                        data-testid="plain-api-token"
                    >{{ $plainTextToken }}</code>
                    <x-ui.button variant="primary" x-on:click="copy" x-bind:disabled="copying" aria-label="Copiar token recién creado">
                        <svg x-show="! copied" class="h-4 w-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-2M6 7h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" /></svg>
                        <svg x-cloak x-show="copied" class="h-4 w-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" /></svg>
                        <span x-text="copied ? 'Copiado ✓' : 'Copiar token'">Copiar token</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="select">Seleccionar</x-ui.button>
                </div>
                <x-ui.button variant="ghost" wire:click="dismissPlainToken">Ya lo guardé; ocultar token</x-ui.button>
            </div>
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <x-ui.card title="Tokens emitidos" description="El valor completo nunca se almacena ni vuelve a mostrarse.">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <x-ui.search-box wire:model.live.debounce.400ms="search" label="Buscar" placeholder="Nombre o propietario" />
                    <x-ui.dropdown-select id="token-status" wire:model.live="status" label="Estado" :value="$status" :options="['' => 'Todos', 'active' => 'Activos', 'expiring' => 'Próximos a expirar', 'expired' => 'Expirados']" />
                    <x-ui.dropdown-select id="token-ability" wire:model.live="ability" label="Ability" :value="$ability" :options="['' => 'Todas'] + $availableAbilities" />
                    <x-ui.dropdown-select id="token-owner" wire:model.live="ownerId" label="Propietario" :value="$ownerId" :options="[0 => 'Todos'] + $users->pluck('name', 'id')->all()" />
                    <x-ui.input id="token-created-from" wire:model.live="createdFrom" type="date" label="Creado desde" />
                    <x-ui.input id="token-created-to" wire:model.live="createdTo" type="date" label="Creado hasta" />
                </div>
            </x-ui.card>

            @if ($selectedTokenIds !== [])
                <x-ui.card class="border-[color:var(--color-danger)]/40">
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

            <x-ui.table id="api-token-list">
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
                            <td class="px-4 py-4"><div class="flex max-w-sm flex-wrap gap-1">@foreach ($token->abilities ?? [] as $tokenAbility)<x-ui.badge tone="info">{{ $tokenAbility }}</x-ui.badge>@endforeach</div></td>
                            <td class="px-4 py-4 text-sm">
                                <x-ui.badge :tone="$expired ? 'danger' : ($expiring ? 'warning' : 'success')">{{ $expired ? 'Expirado' : ($expiring ? 'Próximo a expirar' : 'Activo') }}</x-ui.badge>
                                <p class="mt-2 text-[color:var(--color-text-secondary)]">Último uso: {{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Nunca utilizado' }}</p>
                                <p class="text-[color:var(--color-text-secondary)]">Expira: {{ $token->expires_at?->format('d/m/Y H:i') ?? 'Sin expiración' }}</p>
                            </td>
                            <td class="px-4 py-4"><div class="flex flex-wrap gap-2">
                                <x-ui.button size="sm" variant="secondary" wire:click="rotateToken({{ $token->id }})" wire:loading.attr="disabled">Rotar</x-ui.button>
                                <x-ui.confirm-dialog id="revoke-token-{{ $token->id }}" title="Revocar token" message="{{ $token->name }} dejará de funcionar inmediatamente. Último uso: {{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Nunca utilizado' }}." confirm-label="Revocar token" confirm-action="revokeToken({{ $token->id }})">
                                    <x-slot:trigger><x-ui.button size="sm" variant="danger">Revocar</x-ui.button></x-slot:trigger>
                                </x-ui.confirm-dialog>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12"><x-ui.empty-state title="No hay tokens" description="Crea una credencial con las abilities mínimas necesarias." icon="◈" /></td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            <x-ui.pagination :paginator="$tokens" scroll-to="#api-token-list" />
        </div>

        <x-ui.card title="Crear token" description="El secreto se mostrará una sola vez.">
            <form wire:submit="createToken" class="space-y-4">
                <x-ui.input id="token-name" wire:model="name" label="Nombre" required :error="$errors->first('name')" placeholder="Extensión Chrome - PC principal" />
                <x-ui.textarea id="token-description" wire:model="description" label="Descripción" :error="$errors->first('description')" />
                <x-ui.dropdown-select id="token-owner-create" wire:model="targetUserId" label="Propietario" :value="$targetUserId" :options="$users->pluck('name', 'id')->all()" :error="$errors->first('targetUserId')" />
                <x-ui.input id="token-expiration" wire:model="expirationDate" type="date" :min="now()->addDay()->toDateString()" :max="now()->addYear()->toDateString()" label="Fecha de expiración" required :error="$errors->first('expirationDate')" />
                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">Abilities</legend>
                    @foreach ($availableAbilities as $abilityValue => $abilityLabel)
                        <x-ui.checkbox wire:model="abilities" value="{{ $abilityValue }}"><span class="font-medium">{{ $abilityLabel }}</span><span class="block text-xs text-[color:var(--color-text-muted)]">{{ $abilityValue }}</span></x-ui.checkbox>
                    @endforeach
                    <x-ui.form-error :message="$errors->first('abilities')" />
                </fieldset>
                <x-ui.button type="submit" variant="primary" class="w-full" loading-target="createToken">Generar token</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</div>
