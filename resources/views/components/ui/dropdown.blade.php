@props(['trigger' => null])

<div
    x-data="codeRedFloating({ maxHeight: 320, matchWidth: false, width: 224, align: 'end' })"
    x-on:keydown.escape.stop="closePanel(); $refs.trigger.focus()"
    class="relative"
>
    <button
        x-ref="trigger"
        type="button"
        x-on:click="togglePanel()"
        x-bind:aria-expanded="open.toString()"
        aria-haspopup="menu"
        class="focus-ring"
    >
        {{ $trigger ?? 'Abrir' }}
    </button>

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-cloak
            x-show="open"
            x-bind:style="panelStyle"
            x-bind:data-placement="placement"
            x-on:keydown.escape.stop="closePanel(); $refs.trigger.focus()"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-[0.98]"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-[0.98]"
            role="menu"
            class="layer-popover origin-top overflow-y-auto data-[placement=top]:origin-bottom rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-2 shadow-2xl"
        >
            {{ $slot }}
        </div>
    </template>
</div>
