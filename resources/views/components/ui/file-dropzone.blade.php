@props([
    'name' => 'file',
    'label' => 'Archivo',
    'accept' => null,
    'maxSize' => null,
    'help' => null,
    'required' => false,
    'error' => null,
    'disabled' => false,
])

{{--
    Zona de selección de archivo con drag & drop nativo. NO sube nada por su
    cuenta (sin fetch/XHR): solo puebla el <input type="file"> real, que
    sigue siendo responsabilidad del <form> padre (multipart/form-data,
    @csrf, POST tradicional). Ver x-ui.confirm-dialog para el mismo
    principio aplicado a acciones peligrosas.
--}}
@php $id = $attributes->get('id', $name.'-'.uniqid()); @endphp

<div
    x-data="{
        dragging: false,
        fileName: null,
        fileSize: null,
        disabled: @js((bool) $disabled),
        formatSize(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
        },
        setFiles(fileList) {
            if (this.disabled || !fileList || !fileList.length) return;
            this.fileName = fileList[0].name;
            this.fileSize = this.formatSize(fileList[0].size);
        },
        onDrop(event) {
            if (this.disabled) return;
            this.dragging = false;
            const files = event.dataTransfer.files;
            if (!files || !files.length) return;
            this.$refs.input.files = files;
            this.setFiles(files);
        },
    }"
>
    <x-ui.form-label :for="$id" :required="$required">{{ $label }}</x-ui.form-label>

    <label
        for="{{ $id }}"
        x-bind:class="disabled ? 'opacity-50 cursor-not-allowed border-[color:var(--color-border)]' : (dragging ? 'border-[color:var(--color-brand)] bg-[color:var(--color-brand-soft)]' : 'border-[color:var(--color-border)] hover:border-[color:var(--color-brand)] hover:bg-[color:var(--color-brand-soft)] cursor-pointer')"
        class="mt-2 flex min-h-40 flex-col items-center justify-center rounded-[var(--radius-card)] border border-dashed px-6 py-6 text-center transition focus-within:ring-2 focus-within:ring-[color:var(--color-brand)] {{ $error ? '!border-[color:var(--color-danger)]' : '' }}"
        x-on:dragover.prevent="!disabled && (dragging = true)"
        x-on:dragenter.prevent="!disabled && (dragging = true)"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="onDrop($event)"
    >
        <span class="flex size-10 items-center justify-center rounded-full bg-white/5 text-[color:var(--color-brand-light)]" aria-hidden="true">
            <x-ui.icon name="upload" />
        </span>

        <span class="mt-3 max-w-full truncate text-sm font-medium" x-show="!fileName">Arrastra un archivo o selecciónalo</span>
        <span class="mt-3 max-w-full truncate text-sm font-medium" x-show="fileName" x-text="fileName" x-cloak></span>
        <span class="mt-1 text-xs text-[color:var(--color-text-secondary)]" x-show="fileSize" x-text="fileSize" x-cloak></span>

        @if ($help)
            <span class="mt-1 text-xs text-[color:var(--color-text-secondary)]" x-show="!fileName">{{ $help }}</span>
        @endif
        @if ($maxSize)
            <span class="mt-1 text-xs text-[color:var(--color-text-muted)]">Máximo {{ $maxSize }}</span>
        @endif

        <input
            x-ref="input"
            id="{{ $id }}"
            type="file"
            name="{{ $name }}"
            @if($accept) accept="{{ $accept }}" @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            aria-invalid="{{ $error ? 'true' : 'false' }}"
            x-on:change="setFiles($event.target.files)"
            {{ $attributes->except(['id', 'name', 'accept', 'required', 'disabled'])->merge(['class' => 'sr-only']) }}
        >
    </label>

    <x-ui.form-error :message="$error" />
</div>
