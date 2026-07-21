<div class="space-y-6">
    <x-ui.page-header title="Catálogo de UBIGEO" subtitle="Sincronización manual y validada desde Alanube." />
    @if($errorMessage)<x-ui.alert tone="danger">{{ $errorMessage }}</x-ui.alert>@endif
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card title="Fuente"><p class="font-medium">Alanube</p><p class="mt-2 break-all text-sm text-[color:var(--color-text-muted)]">{{ config('ubigeos.sources.alanube.url') }}</p></x-ui.card>
        <x-ui.card title="Registros"><p class="text-2xl font-semibold">{{ number_format($total) }}</p></x-ui.card>
        <x-ui.card title="Última sincronización"><p>{{ $lastSync ? IlluminateSupportCarbon::parse($lastSync)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') : 'Snapshot local' }}</p></x-ui.card>
    </div>
    @if($lastResult)<x-ui.card title="Último resultado"><dl class="grid gap-3 md:grid-cols-4">@foreach($lastResult as $key => $value)<div><dt class="text-xs text-[color:var(--color-text-muted)]">{{ str_replace('_', ' ', ucfirst($key)) }}</dt><dd>{{ is_bool($value) ? ($value ? 'Sí' : 'No') : $value }}</dd></div>@endforeach</dl></x-ui.card>@endif
    <x-ui.card title="Acciones">
        <div class="flex flex-wrap gap-3">
            <x-ui.button type="button" wire:click="syncNow" loading-target="syncNow">Sincronizar ahora</x-ui.button>
            <x-ui.button type="button" variant="secondary" wire:click="validateCatalog" loading-target="validateCatalog">Validar catálogo</x-ui.button>
            <x-ui.button type="button" variant="danger" wire:click="restoreSnapshot" wire:confirm="¿Restaurar el snapshot local validado?" loading-target="restoreSnapshot">Restaurar snapshot local</x-ui.button>
        </div>
    </x-ui.card>
</div>
