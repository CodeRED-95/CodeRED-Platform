<div class="space-y-6">
    <x-ui.page-header title="Copias de seguridad de agencias" subtitle="Respaldos privados recuperables, separados de las exportaciones funcionales.">
        <x-slot:actions>
            <x-ui.button type="button" wire:click="create" loading-target="create">Crear copia</x-ui.button>
            <x-ui.button href="{{ route('admin.settings.agency-backups') }}" variant="secondary">Ajustes</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($integrityResult)<x-ui.alert tone="info">Integridad: {{ $integrityResult }}</x-ui.alert>@endif

    <x-ui.table>
        <thead><tr><th class="px-5 py-4">Archivo</th><th class="px-5 py-4">Creada</th><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Agencias</th><th class="px-5 py-4">Tamaño</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">SHA-256</th><th class="px-5 py-4">Acciones</th></tr></thead>
        <tbody class="divide-y divide-white/5">
        @forelse($backups as $backup)
            <tr>
                <td class="px-5 py-4 font-mono text-sm">{{ $backup->filename }}</td>
                <td class="px-5 py-4">{{ $backup->created_at?->timezone('America/Lima')->format('d/m/Y H:i:s') }}</td>
                <td class="px-5 py-4">{{ $backup->createdBy?->name ?? 'Sistema' }}</td>
                <td class="px-5 py-4">{{ $backup->record_count }}</td>
                <td class="px-5 py-4">{{ number_format($backup->size_bytes / 1024, 2) }} KB</td>
                <td class="px-5 py-4"><x-ui.badge :tone="$backup->status?->value === 'completed' ? 'success' : ($backup->status?->value === 'failed' ? 'danger' : 'warning')">{{ $backup->status?->value }}</x-ui.badge></td>
                <td class="max-w-48 truncate px-5 py-4 font-mono text-xs" title="{{ $backup->checksum_sha256 }}">{{ $backup->checksum_sha256 ?? '—' }}</td>
                <td class="px-5 py-4"><div class="flex flex-wrap gap-2">
                    @if($backup->status?->value === 'completed')<x-ui.button href="{{ route('admin.agencies.backups.download', $backup) }}" size="sm" variant="secondary">Descargar</x-ui.button>@endif
                    <x-ui.button type="button" size="sm" variant="outline" wire:click="verifyIntegrity({{ $backup->id }})">Verificar integridad</x-ui.button>
                    <x-ui.confirm-dialog id="delete-backup-{{ $backup->id }}" title="Eliminar copia" message="Se eliminarán el archivo privado y su registro. Esta acción no puede deshacerse." confirm-label="Eliminar copia" confirm-action="delete({{ $backup->id }})"><x-slot:trigger><x-ui.button size="sm" variant="danger">Eliminar</x-ui.button></x-slot:trigger></x-ui.confirm-dialog>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-5 py-12"><x-ui.empty-state title="No existen copias" description="Crea la primera copia privada de agencias." /></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
    {{ $backups->links() }}
</div>
