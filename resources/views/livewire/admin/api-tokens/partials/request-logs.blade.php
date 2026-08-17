<x-ui.card title="Historial de consumo" description="No almacena Bearer Tokens ni el DNI en texto plano.">
    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-ui.dropdown-select id="log-client" wire:model.live="logClientId" label="Cliente" :value="$logClientId" :options="[0 => 'Todos'] + $clients->pluck('name', 'id')->all()" />
        <x-ui.dropdown-select id="log-token" wire:model.live="logTokenId" label="Token" :value="$logTokenId" :options="[0 => 'Todos'] + $tokens->pluck('name', 'id')->all()" />
        <x-ui.dropdown-select id="log-service" wire:model.live="logService" label="Servicio" :value="$logService" :options="['' => 'Todos', 'agencias' => 'Agencias', 'dni' => 'DNI', 'ruc' => 'RUC']" />
        <x-ui.input id="log-status" wire:model.live.debounce.400ms="logStatus" label="Estado HTTP" inputmode="numeric" />
        <x-ui.input id="log-from" wire:model.live="logFrom" type="date" label="Desde" />
        <x-ui.input id="log-to" wire:model.live="logTo" type="date" label="Hasta" />
    </div>
    <div class="mt-5 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-[color:var(--color-text-muted)]"><tr><th class="p-3">Fecha</th><th class="p-3">Cliente / token</th><th class="p-3">Usuario delegado</th><th class="p-3">Servicio</th><th class="p-3">HTTP</th><th class="p-3">Duración</th></tr></thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($logs as $log)
                    <tr>
                        <td class="p-3">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="p-3">{{ $log->client?->name ?? 'Usuario heredado' }} · {{ $log->token?->name ?? '#'.$log->token_id }}</td>
                        <td class="p-3">{{ $log->delegatedUser ? $log->delegatedUser->name.' ('.$log->delegatedUser->email.')' : '—' }}{{ $log->is_duplicate_request ? ' · duplicado' : '' }}</td>
                        <td class="p-3">{{ ucfirst($log->service) }}</td>
                        <td class="p-3"><x-ui.badge :tone="$log->status_code < 400 ? 'success' : 'danger'">{{ $log->status_code }}</x-ui.badge></td>
                        <td class="p-3">{{ $log->response_time_ms }} ms</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-[color:var(--color-text-muted)]">Todavía no hay consumo registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-ui.pagination :paginator="$logs" />
</x-ui.card>
