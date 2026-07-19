@props([
    'id' => null,
    'name' => null,
    'label' => 'Estado',
    'value' => null,
    'options' => [],
    'required' => false,
    'disabled' => false,
    'error' => null,
    'placeholder' => 'Selecciona una opción',
    'iconSet' => null,
])

@php
    $controlId = $id ?: 'dropdown-select-'.($name ?: 'field');
    $listboxId = $controlId.'-listbox';
    $labelId = $controlId.'-label';
    $errorId = $controlId.'-error';
    $optionItems = collect($options)
        ->map(fn ($optionLabel, $optionValue): array => [
            'value' => (string) $optionValue,
            'label' => (string) $optionLabel,
        ])
        ->values()
        ->all();
@endphp

<div
    wire:key="{{ $controlId }}-{{ sha1((string) $value) }}"
    class="relative"
    x-data="{
        ...codeRedFloating({ maxHeight: 288 }),
        value: @js((string) $value),
        activeIndex: 0,
        options: @js($optionItems),
        openList() {
            if (@js($disabled)) return;
            this.openPanel();
            const selectedIndex = this.options.findIndex((option) => option.value === this.value);
            this.activeIndex = selectedIndex >= 0 ? selectedIndex : 0;
            this.$nextTick(() => this.keepActiveOptionVisible());
        },
        toggleList() {
            this.open ? this.closeList() : this.openList();
        },
        closeList() {
            this.closePanel();
        },
        move(delta) {
            if (!this.open) {
                this.openList();
                return;
            }

            this.activeIndex = (this.activeIndex + delta + this.options.length) % this.options.length;
            this.$nextTick(() => this.keepActiveOptionVisible());
        },
        selectActive() {
            if (!this.open) {
                this.openList();
                return;
            }

            this.select(this.activeIndex);
        },
        select(index) {
            const option = this.options[index];
            if (!option) return;

            this.value = option.value;
            this.activeIndex = index;
            this.$refs.input.value = option.value;
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.closeList();
            this.$nextTick(() => this.focusTrigger());
        },
        keepActiveOptionVisible() {
            const panel = this.$refs.panel;
            const option = panel?.querySelector(`[data-option-index='${this.activeIndex}']`);
            if (!panel || !option) return;

            const optionTop = option.offsetTop;
            const optionBottom = optionTop + option.offsetHeight;
            if (optionTop < panel.scrollTop) {
                panel.scrollTop = optionTop;
            } else if (optionBottom > panel.scrollTop + panel.clientHeight) {
                panel.scrollTop = optionBottom - panel.clientHeight;
            }
        },
        selectedLabel() {
            return this.options.find((option) => option.value === this.value)?.label ?? @js($placeholder);
        },
    }"
    x-on:keydown.escape.stop="closeList(); focusTrigger()"
>
    <label id="{{ $labelId }}" for="{{ $controlId }}" class="mb-1.5 block text-sm font-medium text-[color:var(--color-text-primary)]">
        {{ $label }}
        @if ($required)
            <span class="text-[color:var(--color-danger)]" aria-hidden="true">*</span>
        @endif
    </label>

    <input
        x-ref="input"
        id="{{ $controlId }}-input"
        type="hidden"
        name="{{ $name }}"
        value="{{ $value }}"
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        {{ $attributes->except('class') }}
    >

    <button
        x-ref="trigger"
        id="{{ $controlId }}"
        type="button"
        role="combobox"
        aria-haspopup="listbox"
        aria-autocomplete="none"
        aria-required="{{ $required ? 'true' : 'false' }}"
        aria-invalid="{{ $error ? 'true' : 'false' }}"
        @if ($error) aria-describedby="{{ $errorId }}" @endif
        aria-labelledby="{{ $labelId }}"
        aria-controls="{{ $listboxId }}"
        x-bind:aria-expanded="open.toString()"
        x-bind:aria-activedescendant="open ? '{{ $controlId }}-option-' + activeIndex : null"
        x-on:click="toggleList()"
        x-on:keydown.enter.prevent="selectActive()"
        x-on:keydown.space.prevent="toggleList()"
        x-on:keydown.arrow-down.prevent="move(1)"
        x-on:keydown.arrow-up.prevent="move(-1)"
        @disabled($disabled)
        @class([
            'flex min-h-12 w-full items-center justify-between gap-3 rounded-[var(--radius-control)] border bg-[color:var(--color-background-elevated)] px-4 py-3 text-left text-sm text-[color:var(--color-text-primary)] shadow-sm transition duration-150 hover:border-slate-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/30 disabled:cursor-not-allowed disabled:opacity-60',
            'border-[color:var(--color-danger)]' => $error,
            'border-[color:var(--color-border)]' => ! $error,
        ])
        x-bind:class="open && '!border-blue-500 ring-2 ring-blue-500/30'"
    >
        <span class="flex min-w-0 items-center gap-3">
                @foreach ($options as $optionValue => $optionLabel)
                <span x-cloak x-show="value === @js($optionValue)" class="contents">
                    <x-ui.select-icon :value="$optionValue" :context="$iconSet" class="h-5 w-5 shrink-0" />
                </span>
            @endforeach
            <span class="truncate font-medium" x-text="selectedLabel()"></span>
        </span>

        <svg class="h-5 w-5 shrink-0 text-[color:var(--color-text-secondary)] transition-transform duration-150" x-bind:class="open && 'rotate-180'" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-cloak
            x-show="open"
            x-bind:style="panelStyle"
            x-bind:data-placement="placement"
            x-on:keydown.escape.stop="closeList(); focusTrigger()"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-[0.98]"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-[0.98]"
            id="{{ $listboxId }}"
            role="listbox"
            aria-labelledby="{{ $labelId }}"
            class="layer-popover origin-top overflow-y-auto data-[placement=top]:origin-bottom rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-1.5 text-[color:var(--color-text-primary)] shadow-xl shadow-black/30"
        >
            @foreach ($options as $optionValue => $optionLabel)
            <button
                id="{{ $controlId }}-option-{{ $loop->index }}"
                type="button"
                role="option"
                data-option-index="{{ $loop->index }}"
                x-bind:aria-selected="(value === @js($optionValue)).toString()"
                x-on:mouseenter="activeIndex = {{ $loop->index }}"
                x-on:click="select({{ $loop->index }})"
                class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition duration-100 focus:outline-none"
                x-bind:class="{
                    'bg-blue-600 text-white': value === @js($optionValue),
                    'bg-slate-800 text-white': value !== @js($optionValue) && activeIndex === {{ $loop->index }},
                    'text-slate-100 hover:bg-slate-800 hover:text-white': value !== @js($optionValue),
                }"
            >
                <span class="flex min-w-0 items-center gap-3">
                    <x-ui.select-icon :value="$optionValue" :context="$iconSet" class="h-5 w-5 shrink-0" />
                    <span class="truncate font-medium">{{ $optionLabel }}</span>
                </span>

                <svg x-cloak x-show="value === @js($optionValue)" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            @endforeach
        </div>
    </template>

    @if ($error)
        <x-ui.form-error :id="$errorId" :message="$error" />
    @endif
</div>
