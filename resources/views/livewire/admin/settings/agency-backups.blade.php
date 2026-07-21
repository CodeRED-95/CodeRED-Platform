<div class="space-y-6">
    <x-ui.page-header title="Ajustes de copias de seguridad" subtitle="La limpieza automática permanece desactivada hasta que la habilites expresamente." />
    <x-ui.card title="Retención">
        <form wire:submit="save" class="grid gap-5 md:grid-cols-2">
            <x-ui.input type="number" min="1" max="100" wire:model="maximumBackups" label="Cantidad máxima de backups" :error="$errors->first('maximumBackups')" />
            <x-ui.input type="number" min="1" max="3650" wire:model="retentionDays" label="Días de retención" :error="$errors->first('retentionDays')" />
            <div class="md:col-span-2"><x-ui.toggle wire:model="autoCleanup" label="Eliminar automáticamente copias antiguas" description="Siempre se conservará como mínimo una copia." /></div>
            <div class="md:col-span-2"><x-ui.button type="submit" loading-target="save">Guardar configuración</x-ui.button></div>
        </form>
    </x-ui.card>
</div>
