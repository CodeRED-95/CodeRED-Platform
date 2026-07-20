<div class="space-y-6">
    <x-ui.page-header title="Documentación API" subtitle="Controla si la referencia técnica puede consultarse sin iniciar sesión." />
    <x-ui.card title="Visibilidad">
        <form wire:submit="save" class="space-y-5">
            <x-ui.toggle wire:model="public" label="Documentación pública" description="Si se desactiva, las rutas /docs exigirán una sesión web activa." />
            <x-ui.alert tone="warning">La documentación nunca contiene tokens, API keys, clientes ni respuestas de producción.</x-ui.alert>
            <x-ui.button type="submit" loading-target="save">Guardar configuración</x-ui.button>
        </form>
    </x-ui.card>
</div>
