@props(['label' => 'Seleccionar archivo', 'description' => null, 'error' => null, 'accept' => null, 'required' => false])
@php $id = $attributes->get('id', 'upload-'.uniqid()); @endphp
<div x-data="{ fileName: null, uploading: false, progress: 0 }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false; progress = 100" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
    <x-ui.form-label :for="$id" :required="$required">{{ $label }}</x-ui.form-label>
    <label for="{{ $id }}" class="focus-within:ring-2 focus-within:ring-[color:var(--color-brand)] mt-2 flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-[var(--radius-card)] border border-dashed px-6 py-5 text-center transition hover:border-[color:var(--color-brand)] hover:bg-[color:var(--color-brand-soft)] {{ $error ? 'border-[color:var(--color-danger)]' : 'border-[color:var(--color-border)]' }}">
        <span class="flex size-10 items-center justify-center rounded-full bg-white/5 text-[color:var(--color-brand-light)]" aria-hidden="true">⇪</span>
        <span class="mt-3 text-sm font-medium" x-text="fileName || 'Arrastra un archivo o selecciónalo'"></span>
        @if($description)<span class="mt-1 text-xs text-[color:var(--color-text-secondary)]">{{ $description }}</span>@endif
        <input id="{{ $id }}" type="file" @if($accept) accept="{{ $accept }}" @endif @if($required) required @endif aria-invalid="{{ $error ? 'true' : 'false' }}" x-on:change="fileName = $event.target.files[0]?.name || ''" {{ $attributes->except(['id', 'accept'])->merge(['class' => 'sr-only']) }}>
    </label>
    <div x-cloak x-show="uploading" class="mt-2" role="status" aria-live="polite"><div class="mb-1 flex justify-between text-xs text-[color:var(--color-text-secondary)]"><span>Subiendo archivo…</span><span x-text="`${progress}%`"></span></div><div class="h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full bg-[color:var(--color-brand)] transition-[width]" x-bind:style="`width: ${progress}%`"></div></div></div>
    <x-ui.form-error :message="$error" />
</div>
