<div>
    <x-ui.card>
        <x-ui.section-header title="Restablecer contraseña" subtitle="Genera una contraseña temporal segura." />
        <div class="mt-4 flex items-center gap-3">
            <x-ui.confirm-dialog
                id="reset-user-password"
                title="Restablecer contraseña"
                message="Se invalidará la contraseña actual y se generará una nueva contraseña temporal."
                confirm-label="Restablecer"
                confirm-action="resetPassword"
            >
                <x-slot:trigger>
                    <x-ui.button type="button" variant="primary">Restablecer</x-ui.button>
                </x-slot:trigger>
            </x-ui.confirm-dialog>
            <span class="text-sm text-[color:var(--color-text-secondary)]">La contraseña se genera y no se vuelve a mostrar.</span>
        </div>
    </x-ui.card>
</div>
