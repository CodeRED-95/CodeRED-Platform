@props([
    'entry',
])

@php
    $action = $entry->action ?? $entry->event ?? 'updated';
    $actor = $entry->actor ?? $entry->performer ?? null;
    $description = $entry->description ?? null;
    $metadata = $entry->metadata ?? [];
    $metadata = is_array($metadata) ? $metadata : [];

    $actions = [
        'created' => 'Creación',
        'updated' => 'Actualización',
        'roles_updated' => 'Cambio de roles',
        'deleted' => 'Envío a papelera',
        'restored' => 'Restauración',
        'force_deleted' => 'Eliminación definitiva',
        'moved' => 'Traslado',
        'move_cancelled' => 'Cancelación de traslado',
        'viewed' => 'Solicitud visualizada',
        'token_type_selected' => 'Tipo de token seleccionado',
        'token_type_changed' => 'Tipo de token cambiado',
        'approved' => 'Solicitud aprobada',
        'token_generated' => 'Token generado',
        'rejected' => 'Solicitud rechazada',
        'rejection_reason_recorded' => 'Motivo registrado',
        'cancelled' => 'Solicitud cancelada',
        'expired' => 'Solicitud vencida',
        'delivery_confirmed' => 'Entrega confirmada',
        'token_retrieved' => 'Token recuperado',
        'invalid_transition' => 'Transición inválida',
    ];
    $labels = [
        'name' => 'Nombre',
        'email' => 'Correo',
        'status' => 'Estado',
        'roles' => 'Roles',
        'address' => 'Dirección',
        'department' => 'Departamento',
        'province' => 'Provincia',
        'district' => 'Distrito',
        'phone' => 'Teléfono',
        'updated_by' => 'Actualizado por',
        'deleted_at' => 'Papelera',
        'credentials' => 'Credenciales',
        'token_type' => 'Tipo aprobado',
        'requested_token_type' => 'Tipo solicitado',
        'abilities' => 'Permisos',
        'expires_at' => 'Expira',
        'reason' => 'Motivo',
        'delivery_channel' => 'Canal',
    ];
    $sensitive = fn (string $key): bool => str_contains(strtolower($key), 'token') && ! in_array($key, ['token_type', 'requested_token_type'], true)
        || str_contains(strtolower($key), 'secret')
        || str_contains(strtolower($key), 'password')
        || str_contains(strtolower($key), 'authorization')
        || str_contains(strtolower($key), 'key');
    $oldValues = $entry->old_values ?? [];
    $newValues = $entry->new_values ?? [];
    $changedFields = $entry->changed_fields ?? array_values(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))));
    $changedFields = array_values(array_filter($changedFields, fn ($field) => ! in_array($field, ['updated_at', 'created_at', 'data_version'], true) && ! $sensitive((string) $field)));
    $metadata = array_filter($metadata, fn ($value, $key) => ! $sensitive((string) $key), ARRAY_FILTER_USE_BOTH);
    $formatValue = function (mixed $value): string {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value));
        }

        return (string) $value;
    };
@endphp

<article class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.03] p-4">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div>
            <p class="font-medium">{{ $actions[$action] ?? ucfirst(str_replace('_', ' ', $action)) }}</p>
            @if ($description)
                <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">{{ $description }}</p>
            @endif
            <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">
                {{ $actor?->name ?? 'Sistema' }}
                @if ($actor?->email)
                    · {{ $actor->email }}
                @endif
            </p>
        </div>
        <time class="shrink-0 text-xs text-[color:var(--color-text-muted)]" datetime="{{ $entry->created_at?->toIso8601String() }}">
            {{ $entry->created_at?->format('d/m/Y H:i:s') }}
        </time>
    </div>

    @if ($changedFields !== [])
        <dl class="mt-4 space-y-2 border-t border-white/5 pt-3 text-sm">
            @foreach ($changedFields as $field)
                <div class="grid gap-1 sm:grid-cols-[9rem_1fr]">
                    <dt class="font-medium text-[color:var(--color-text-secondary)]">{{ $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)) }}</dt>
                    <dd class="min-w-0 break-words">
                        @if ($field === 'credentials')
                            Credenciales actualizadas
                        @else
                            <span class="text-[color:var(--color-text-muted)]">{{ $formatValue($oldValues[$field] ?? null) }}</span>
                            <span class="mx-2" aria-hidden="true">→</span>
                            <span>{{ $formatValue($newValues[$field] ?? null) }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if ($metadata !== [])
        <dl class="mt-4 space-y-2 border-t border-white/5 pt-3 text-sm">
            @foreach ($metadata as $field => $value)
                <div class="grid gap-1 sm:grid-cols-[9rem_1fr]">
                    <dt class="font-medium text-[color:var(--color-text-secondary)]">{{ $labels[$field] ?? ucfirst(str_replace('_', ' ', (string) $field)) }}</dt>
                    <dd class="min-w-0 break-words">{{ $formatValue($value) }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[color:var(--color-text-muted)]">
        <span>IP: {{ $entry->ip_address ?? 'No disponible' }}</span>
        @if ($entry->user_agent)
            <span class="truncate" title="{{ $entry->user_agent }}">Agente: {{ $entry->user_agent }}</span>
        @endif
    </div>
</article>
