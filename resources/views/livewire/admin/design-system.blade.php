<div class="space-y-8">
    <x-ui.page-header
        title="CodeRED Design System"
        subtitle="Referencia interna para colores, componentes y patrones visuales del proyecto."
    />

    <x-ui.card>
        <x-ui.section-header title="Paleta oficial" description="Tokens semánticos usados por la interfaz." />
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['name' => 'Background', 'value' => 'var(--color-background)'],
                ['name' => 'Surface', 'value' => 'var(--color-surface)'],
                ['name' => 'Brand', 'value' => 'var(--color-brand)'],
                ['name' => 'Accent ivory', 'value' => 'var(--color-accent-ivory)'],
            ] as $color)
                <div class="rounded-[var(--radius-card)] border border-white/10 p-4">
                    <div class="h-20 rounded-2xl border border-white/10" style="background: {{ $color['value'] }}"></div>
                    <p class="mt-3 font-medium">{{ $color['name'] }}</p>
                    <code class="text-xs text-[color:var(--color-text-secondary)]">{{ $color['value'] }}</code>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Botones" />
            <div class="mt-5 flex flex-wrap gap-3">
                <x-ui.button variant="primary">Primario</x-ui.button>
                <x-ui.button variant="secondary">Secundario</x-ui.button>
                <x-ui.button variant="outline">Outline</x-ui.button>
                <x-ui.button variant="ghost">Ghost</x-ui.button>
                <x-ui.button variant="danger">Peligro</x-ui.button>
            </div>
        </x-ui.card>
        <x-ui.card>
            <x-ui.section-header title="Estados y badges" />
            <div class="mt-5 flex flex-wrap gap-3">
                <x-ui.badge tone="success">Activa</x-ui.badge>
                <x-ui.badge tone="neutral">Inactiva</x-ui.badge>
                <x-ui.badge tone="info">En revisión</x-ui.badge>
                <x-ui.badge tone="warning">Trasladada</x-ui.badge>
                <x-ui.badge tone="brand">Centro de Operaciones</x-ui.badge>
                <x-ui.badge tone="ivory">Grande</x-ui.badge>
            </div>
        </x-ui.card>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Formularios" />
            <div class="mt-5 space-y-4">
                <x-ui.input label="Correo" placeholder="admin@codered.local" />
                <x-ui.search-box label="Buscar" placeholder="Buscar agencia o usuario..." />
                <x-ui.status-select label="Estado" value="active" :options="['active' => 'Activa', 'under_review' => 'En revisión']" />
                <x-ui.textarea label="Observaciones" rows="3" placeholder="Notas internas..." />
                <x-ui.toggle>Centro de Operaciones</x-ui.toggle>
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="Alertas" />
            <div class="mt-5 space-y-3">
                <x-ui.alert tone="info">Información general para el usuario.</x-ui.alert>
                <x-ui.alert tone="success">Operación completada correctamente.</x-ui.alert>
                <x-ui.alert tone="warning">Revisa algunos campos antes de continuar.</x-ui.alert>
                <x-ui.alert tone="danger">Algo salió mal.</x-ui.alert>
            </div>
        </x-ui.card>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Tarjetas y empty states" />
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-ui.stat-card label="Total de agencias" value="128" tone="brand" />
                <x-ui.empty-state title="Sin resultados" description="Aquí aparecerán los datos cuando existan." icon="⌁" />
            </div>
        </x-ui.card>
        <x-ui.card>
            <x-ui.section-header title="Logo y marca" />
            <div class="mt-5 space-y-4">
                <x-ui.logo variant="full" class="h-14" />
                <x-ui.logo variant="symbol" class="h-12 w-12 rounded-2xl" />
                <x-ui.logo variant="square" class="h-14 w-14 rounded-2xl" />
            </div>
        </x-ui.card>
    </div>

    <x-ui.card>
        <x-ui.section-header title="Confirmaciones" description="Acciones sensibles sin alertas nativas del navegador." />
        <div class="mt-5">
            <x-ui.confirm-dialog
                id="design-system-confirmation"
                title="Confirmar acción"
                message="Verifica la información antes de continuar."
            >
                <x-slot:trigger>
                    <x-ui.button variant="danger">Abrir confirmación</x-ui.button>
                </x-slot:trigger>
            </x-ui.confirm-dialog>
        </div>
    </x-ui.card>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Carga y skeletons" description="Feedback para operaciones y contenido asíncrono." />
            <div class="mt-5 space-y-4">
                <div class="flex items-center gap-3"><x-ui.spinner /> <span class="text-sm text-[color:var(--color-text-secondary)]">Procesando datos…</span></div>
                <x-ui.skeleton variant="text" :rows="3" />
            </div>
        </x-ui.card>
        <x-ui.card>
            <x-ui.section-header title="Toasts" description="Mensajes temporales globales y accesibles." />
            <div class="mt-5">
                <x-ui.button variant="secondary" x-on:click="window.dispatchEvent(new CustomEvent('toast', { detail: { tone: 'success', message: 'Operación completada.' } }))">Mostrar toast</x-ui.button>
            </div>
        </x-ui.card>
    </div>
</div>
