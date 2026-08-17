<x-ui.card title="Tokens revocados" description="Historial sin secreto ni hash reutilizable.">
    <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($revokedTokens as $revokedToken)
            <div class="rounded-xl border border-white/10 p-3">
                <div class="flex justify-between gap-2">
                    <span class="font-medium">{{ $revokedToken->name }}</span>
                    <x-ui.badge tone="danger">Revocado</x-ui.badge>
                </div>
                <p class="text-sm text-[color:var(--color-text-secondary)]">{{ $revokedToken->owner_name }} · #{{ $revokedToken->original_token_id }}</p>
                <p class="text-xs text-[color:var(--color-text-muted)]">{{ implode(', ', $revokedToken->abilities) }} · {{ $revokedToken->revoked_at?->format('d/m/Y H:i') }}</p>
            </div>
        @empty
            <p class="text-sm text-[color:var(--color-text-muted)]">No hay tokens revocados.</p>
        @endforelse
    </div>
</x-ui.card>
