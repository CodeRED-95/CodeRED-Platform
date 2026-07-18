<label class="flex items-start gap-3 text-sm text-[color:var(--color-text-primary)]">
    <input {{ $attributes->merge(['class' => 'mt-0.5 h-4 w-4 rounded border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-brand)] focus-ring']) }} type="checkbox">
    <span>{{ $slot }}</span>
</label>
