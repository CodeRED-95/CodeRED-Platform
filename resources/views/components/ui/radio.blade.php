@props(['label' => null, 'description' => null])
<label class="flex cursor-pointer items-start gap-3 rounded-[var(--radius-control)] p-2 text-sm transition hover:bg-white/5">
    <input type="radio" {{ $attributes->merge(['class' => 'mt-0.5 size-4 border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-brand)] focus-ring']) }}>
    <span><span class="block font-medium text-[color:var(--color-text-primary)]">{{ $label ?? $slot }}</span>@if($description)<span class="mt-0.5 block text-xs text-[color:var(--color-text-secondary)]">{{ $description }}</span>@endif</span>
</label>
