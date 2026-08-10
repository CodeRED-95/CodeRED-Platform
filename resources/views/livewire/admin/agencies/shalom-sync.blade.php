<div class="space-y-6">
    <x-ui.page-header
        title="Sincronización Shalom"
        subtitle="Carga el archivo Chosen para analizar diferencias y encolar la importación sin exponer el flujo a la interfaz del navegador."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.agencies.index') }}" variant="secondary">Volver a agencias</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Archivo de sincronización" description="Acepta JSON, TXT o HTML con elementos <li>. El archivo se conserva dentro de la ejecución.">
        <form wire:submit="sync" class="space-y-5">
            <x-ui.file-upload
                id="chosenFile"
                wire:model="chosenFile"
                label="Archivo Chosen"
                accept=".json,.txt,.html,text/plain,application/json,text/html"
                description="Selecciona el archivo exportado desde Shalom para iniciar el análisis."
                :error="$errors->first('chosenFile')"
                required
            />

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary" loading-target="sync" loading-label="Enviando a la cola…">Iniciar análisis</x-ui.button>
                <x-ui.button href="{{ route('admin.agencies.backups.index') }}" variant="secondary">Ir a copias</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.alert tone="info" title="Ejecución en segundo plano">
        El worker debe escuchar la cola <strong>agency-imports</strong>. El contenedor extractor solo mostrará solicitudes cuando el job sea tomado por el worker.
    </x-ui.alert>
</div>
