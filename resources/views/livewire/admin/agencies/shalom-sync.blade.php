<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Sincronización Shalom</h1>
        <p class="mt-2 text-[color:var(--color-text-secondary)]">Carga el archivo Chosen. La extracción se ejecutará en segundo plano y antes de importar verás todas las diferencias.</p>
    </div>

    <x-ui.card>
        <form wire:submit="sync" class="space-y-5">
            <div>
                <label for="chosenFile" class="mb-2 block text-sm font-medium text-white">Archivo Chosen</label>
                <input id="chosenFile" type="file" wire:model="chosenFile" accept=".json,.txt,.html,text/plain,application/json,text/html" class="block w-full rounded-lg border border-slate-700 bg-slate-950/40 p-3 text-sm text-white">
                <p class="mt-2 text-xs text-[color:var(--color-text-secondary)]">Acepta JSON, TXT o HTML con elementos &lt;li&gt;. El archivo se conserva dentro de la ejecución.</p>
                @error('chosenFile') <p class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary" loading-target="sync" loading-label="Enviando a la cola…">Iniciar análisis</x-ui.button>
                <x-ui.button href="{{ route('admin.agencies.import') }}" variant="secondary">Volver a importaciones</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.alert tone="info">El worker debe escuchar la cola <strong>agency-imports</strong>. El contenedor extractor solo mostrará solicitudes cuando el Job sea tomado por el worker.</x-ui.alert>
</div>
