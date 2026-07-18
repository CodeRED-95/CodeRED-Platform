<label class="inline-flex items-center gap-3 text-sm text-[color:var(--color-text-primary)]">
    <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-white/10 ring-1 ring-inset ring-white/10">
        <input type="checkbox" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-5 peer-checked:bg-[color:var(--color-brand)]"></span>
    </span>
    <span>{{ $slot }}</span>
</label>
