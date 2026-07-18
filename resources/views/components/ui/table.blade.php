<div class="overflow-hidden rounded-[var(--radius-card)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)]">
    <div class="overflow-x-auto">
        <table {{ $attributes->merge(['class' => 'min-w-full text-left text-sm']) }}>
            {{ $slot }}
        </table>
    </div>
</div>
