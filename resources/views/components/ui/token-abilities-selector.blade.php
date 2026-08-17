@props([
    'abilities' => [],
    'availableAbilities' => [],
    'allowedAbilities' => [],
    'selectedAbilities' => [],
    'error' => null,
])

<fieldset class="space-y-3">
    <legend class="text-sm font-medium">Permisos del token</legend>
    <p class="text-xs text-[color:var(--color-text-muted)]">Marca las abilities que necesita la integración. Solo puedes conceder las que tu usuario administra.</p>
    <div class="flex flex-wrap gap-2">
        @forelse ($selectedAbilities as $ability)
            <x-ui.badge tone="info">{{ $ability['label'] }} · {{ $ability['ability'] }}</x-ui.badge>
        @empty
            <x-ui.badge tone="warning">Ningún permiso seleccionado</x-ui.badge>
        @endforelse
    </div>
    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($availableAbilities as $ability)
            <label class="rounded-[var(--radius-control)] border border-white/10 bg-white/5 p-4 transition hover:bg-white/10">
                <div class="flex items-start gap-3">
                    <input type="checkbox" wire:model.live="abilities" value="{{ $ability['ability'] }}" class="mt-1 h-4 w-4 rounded border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-brand)] focus-ring" @disabled(! empty($ability['disabled']))>
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $ability['label'] }}</span>
                            <x-ui.badge tone="info">{{ $ability['ability'] }}</x-ui.badge>
                        </div>
                        <p class="text-xs text-[color:var(--color-text-muted)]">{{ $ability['description'] }}</p>
                        @if (! empty($ability['disabled']))
                            <p class="text-xs text-[color:var(--color-danger)]">No autorizado para tu usuario.</p>
                        @endif
                    </div>
                </div>
            </label>
        @endforeach
    </div>
    @if (in_array('*', $allowedAbilities, true))
        <div class="rounded-[var(--radius-control)] border border-[color:var(--color-warning)]/30 bg-[color:var(--color-warning)]/10 p-4 text-sm">
            <strong>Acceso completo:</strong> tu usuario puede otorgar cualquier ability disponible. Úsalo solo cuando sea necesario.
        </div>
    @endif
    <x-ui.form-error :message="$error" />
</fieldset>
