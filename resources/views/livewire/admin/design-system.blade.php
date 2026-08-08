<div class="space-y-8">
    <x-ui.page-header
        title="CodeRED Design System"
        subtitle="Referencia interna para colores, componentes y patrones visuales del proyecto."
    />

    <x-ui.card>
        <x-ui.section-header title="Paleta oficial" description="Tokens semánticos usados por la interfaz." />
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['name' => 'Background', 'value' => 'var(--color-background)', 'class' => 'token-swatch-background'],
                ['name' => 'Surface', 'value' => 'var(--color-surface)', 'class' => 'token-swatch-surface'],
                ['name' => 'Brand', 'value' => 'var(--color-brand)', 'class' => 'token-swatch-brand'],
                ['name' => 'Accent ivory', 'value' => 'var(--color-accent-ivory)', 'class' => 'token-swatch-ivory'],
            ] as $color)
                <div class="rounded-[var(--radius-card)] border border-white/10 p-4">
                    <div class="h-20 rounded-[var(--radius-card)] border border-white/10 {{ $color['class'] }}"></div>
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
                <x-ui.button variant="success">Éxito</x-ui.button>
                <x-ui.button variant="warning">Advertencia</x-ui.button>
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

    <x-ui.card title="Carga de archivos y selección" description="Controles reutilizables con foco, ayuda y errores asociados.">
        <div class="grid gap-5 lg:grid-cols-2">
            <x-ui.file-upload label="Archivo de ejemplo" description="JSON o TXT según el flujo." />
            <fieldset><legend class="mb-2 text-sm font-medium">Modo de ejecución</legend><x-ui.radio name="demo-mode" value="safe" label="Modo seguro" description="Valida antes de persistir." /><x-ui.radio name="demo-mode" value="direct" label="Modo directo" /></fieldset>
        </div>
    </x-ui.card>

    <x-ui.card title="Tabla y paginación" description="Superficie compartida, hover semántico y encabezado consistente.">
        <x-ui.table caption="Ejemplo de tabla del sistema"><thead><tr><th>Elemento</th><th>Estado</th><th>Origen</th></tr></thead><tbody><tr><td>Agencia de ejemplo</td><td><x-ui.badge tone="success">Activa</x-ui.badge></td><td>CodeRED</td></tr></tbody></x-ui.table>
    </x-ui.card>

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
            <x-ui.section-header title="Alertas" description="icon + title + descripción + actions opcionales, un tono por cada estado." />
            <div class="mt-5 space-y-3">
                <x-ui.alert tone="neutral">Mensaje informativo sin énfasis particular.</x-ui.alert>
                <x-ui.alert tone="info">Información general para el usuario.</x-ui.alert>
                <x-ui.alert tone="success">Operación completada correctamente.</x-ui.alert>
                <x-ui.alert tone="warning">Revisa algunos campos antes de continuar.</x-ui.alert>
                <x-ui.alert tone="danger">Algo salió mal.</x-ui.alert>
                <x-ui.alert tone="success" title="Backup importado correctamente">
                    ruc_backup_2026-08-08-131307.dump — 442.9 MiB · 18,316,242 registros
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary">Ver en la lista</x-ui.button>
                        <x-ui.button size="sm" variant="ghost">Importar otro</x-ui.button>
                    </x-slot:actions>
                </x-ui.alert>
            </div>
            <p class="mt-3 text-xs text-[color:var(--color-text-secondary)]">
                <code>&lt;x-ui.alert tone="..." title="..."&gt;</code>descripción<code>&lt;x-slot:actions&gt;...&lt;/x-slot:actions&gt;&lt;/x-ui.alert&gt;</code>.
                Si el caller pasa su propio <code>x-show</code> (p. ej. dentro de una máquina de estados), se combina automáticamente con la visibilidad interna del botón de descartar — nunca se duplica el atributo.
            </p>
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

    {{-- ================================================================
         Feedback & Operations
         Componentes para acciones peligrosas y operaciones largas: mismos
         principios que RUC Backup (ver /admin/ruc/backups), demostrados
         aquí de forma genérica y reutilizable en cualquier módulo.
    ================================================================= --}}
    <x-ui.page-header title="Feedback &amp; Operations" subtitle="Confirmaciones, progreso y estado de operaciones largas — sin alert()/confirm() nativos del navegador." />

    <x-ui.card>
        <x-ui.section-header title="Botones con estado loading" description="El spinner es parte del componente: :loading en Blade, o wire:loading vía loading-target." />
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <x-ui.button variant="primary" :loading="false">Normal</x-ui.button>
            <x-ui.button variant="primary" :loading="true">Cargando</x-ui.button>
            <x-ui.button variant="warning" :loading="true" loading-label="Restaurando…">Restaurar</x-ui.button>
            <x-ui.button variant="danger" disabled>Deshabilitado</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card>
        <x-ui.section-header title="ConfirmDialog — tonos" description="Header con ícono + título, body con metadata, footer Cancelar/Acción. Reemplaza window.confirm() en todo el panel." />
        <div class="mt-5 flex flex-wrap gap-3">
            <x-ui.confirm-dialog id="ds-confirm-neutral" tone="neutral" title="Confirmación estándar" message="Verifica la información antes de continuar.">
                <x-slot:trigger><x-ui.button variant="secondary">Confirmación estándar</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>

            <x-ui.confirm-dialog id="ds-confirm-warning" tone="warning" icon="restore" title="Restaurar datos" confirm-label="Restaurar" message="Esta acción reemplazará los datos actuales por los del backup seleccionado.">
                <dl class="mt-2 space-y-2">
                    <div class="flex justify-between gap-4"><dt class="text-[color:var(--color-text-secondary)]">Backup</dt><dd class="font-mono text-xs">ruc_backup_2026-08-07.dump</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-[color:var(--color-text-secondary)]">Registros</dt><dd class="font-medium">18,316,242</dd></div>
                </dl>
                <x-ui.alert tone="warning" class="mt-4">Se creará un Safety Backup automáticamente antes de continuar.</x-ui.alert>
                <x-slot:trigger><x-ui.button variant="warning">Restaurar datos</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>

            <x-ui.confirm-dialog id="ds-confirm-danger" tone="danger" icon="trash" title="Eliminar registro" confirm-label="Eliminar" message="Esta acción no se puede deshacer.">
                <x-slot:trigger><x-ui.button variant="danger">Eliminar registro</x-ui.button></x-slot:trigger>
            </x-ui.confirm-dialog>
        </div>
        <p class="mt-4 text-xs text-[color:var(--color-text-secondary)]">
            Dos modos de confirmación: Livewire (<code>confirm-action="metodo"</code>, usado arriba) o formulario tradicional
            (<code>form="id-del-form"</code>, usado en RUC Backup — ver <code>resources/views/ruc/admin/backups/index.blade.php</code>).
            El segundo ejecuta <code>form.requestSubmit()</code> sobre un <code>&lt;form method="POST"&gt;@csrf&lt;/form&gt;</code> real; nunca fetch/AJAX.
        </p>
    </x-ui.card>

    <x-ui.card>
        <x-ui.section-header title="FileDropzone" description="Drag &amp; drop nativo. No sube nada por su cuenta: el <form> padre es responsable del submit." />
        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--color-text-secondary)]">Vacío</p>
                <x-ui.file-dropzone name="demo-empty" label="Archivo de backup" accept=".dump,.gz" help="Archivos .dump / .sql.gz" max-size="5000 MB" />
            </div>
            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--color-text-secondary)]">Con error</p>
                <x-ui.file-dropzone name="demo-error" label="Archivo de backup" accept=".dump,.gz" error="El archivo debe tener extensión .dump o .gz." />
            </div>
            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--color-text-secondary)]">Deshabilitado</p>
                <x-ui.file-dropzone name="demo-disabled" label="Archivo de backup" disabled help="No disponible mientras haya una operación en curso." />
            </div>
            <div x-data="{ demo: true }">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--color-text-secondary)]">Seleccionado (simulado)</p>
                <x-ui.file-dropzone name="demo-selected" label="Archivo de backup" help="Arrastra un archivo o selecciónalo" />
            </div>
            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--color-text-secondary)]">Múltiple (partes de un backup)</p>
                <x-ui.file-dropzone name="demo-multiple" label="Partes del backup" multiple help="Selecciona varias partes .partNNNN juntas" />
            </div>
        </div>
    </x-ui.card>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Progress" description="value/max representan progreso real; sin dato real, usar indeterminate." />
            <div class="mt-5 space-y-5">
                <x-ui.progress :value="25" label="Importando datos" />
                <x-ui.progress :value="65" label="Procesando registros" tone="info" />
                <x-ui.progress :value="100" label="Completado" tone="success" />
                <x-ui.progress indeterminate label="Restaurando datos (progreso indeterminado)" tone="warning" />
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="ProcessSteps" description="Estados: completed, active, pending, failed." />
            <div class="mt-5 space-y-6">
                <x-ui.process-steps :steps="[
                    ['label' => 'Validando archivo', 'status' => 'completed'],
                    ['label' => 'Creando backup', 'status' => 'completed'],
                    ['label' => 'Restaurando', 'status' => 'active'],
                    ['label' => 'Verificando', 'status' => 'pending'],
                    ['label' => 'Finalizando', 'status' => 'pending'],
                ]" />
                <div class="border-t border-[color:var(--color-border-subtle)] pt-4">
                    <x-ui.process-steps :steps="[
                        ['label' => 'Validando', 'status' => 'completed'],
                        ['label' => 'Restauración fallida', 'status' => 'failed', 'description' => 'ruc_records no se modificó: rollback automático.'],
                        ['label' => 'Verificando', 'status' => 'pending'],
                    ]" />
                </div>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card>
        <x-ui.section-header title="OperationStatus" description="Composición de título + badge + tiempo transcurrido + progreso/pasos, para operaciones largas (backups, restores, imports, syncs)." />
        <div class="mt-5">
            <x-ui.operation-status title="Restauración en progreso" status="running" elapsed="02:14">
                <x-slot:progress>
                    <x-ui.progress :value="50" label="Restaurando datos de ruc_records" tone="warning" />
                </x-slot:progress>
                <x-slot:steps>
                    <x-ui.process-steps :steps="[
                        ['label' => 'Backup validado', 'status' => 'completed'],
                        ['label' => 'Checksum verificado', 'status' => 'completed'],
                        ['label' => 'Safety Backup creado', 'status' => 'completed'],
                        ['label' => 'Restaurando datos', 'status' => 'active'],
                        ['label' => 'Verificando', 'status' => 'pending'],
                        ['label' => 'Finalizando', 'status' => 'pending'],
                    ]" />
                </x-slot:steps>
            </x-ui.operation-status>
        </div>
    </x-ui.card>

    <x-ui.card>
        <x-ui.section-header
            title="Estados terminales de una operación"
            description="Ejemplo real: importación multipart de RUC (/admin/ruc/backups). Solo referencia — en la página real, la máquina de estados (Alpine) garantiza que un único bloque se renderiza a la vez; aquí se muestran los tres lado a lado únicamente con fines de catálogo."
        />
        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--color-text-secondary)]">completed</p>
                <x-ui.alert tone="success" title="Backup importado correctamente">
                    <p class="font-mono text-xs">ruc_backup_2026-08-08-131307.dump</p>
                    <p class="mt-0.5 text-xs text-[color:var(--color-text-secondary)]">442.9 MiB · 18,316,242 registros</p>
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary">Ver en la lista</x-ui.button>
                        <x-ui.button size="sm" variant="ghost">Importar otro</x-ui.button>
                    </x-slot:actions>
                </x-ui.alert>
            </div>
            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--color-text-secondary)]">failed</p>
                <x-ui.alert tone="danger" title="Error al importar el backup">
                    Checksum incorrecto en part0004.
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary">Reintentar</x-ui.button>
                        <x-ui.button size="sm" variant="ghost">Empezar de nuevo</x-ui.button>
                    </x-slot:actions>
                </x-ui.alert>
            </div>
            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[color:var(--color-text-secondary)]">cancelled</p>
                <x-ui.alert tone="neutral" title="Importación cancelada">
                    Las partes temporales fueron eliminadas.
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary">Empezar de nuevo</x-ui.button>
                    </x-slot:actions>
                </x-ui.alert>
            </div>
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
