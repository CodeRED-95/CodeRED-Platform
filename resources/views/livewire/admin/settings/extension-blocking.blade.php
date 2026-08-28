<div class="space-y-6">
    <x-ui.page-header
        title="Bloqueo de la extensión"
        subtitle="Define qué rutas de shalomcontrol.com se bloquean y en qué horario. Aplica a todas las extensiones conectadas con un token válido." />

    <x-ui.card title="Cómo se aplica">
        <div class="grid gap-4 text-sm md:grid-cols-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-[color:var(--color-text-muted)]">Versión publicada</p>
                <p class="mt-1 font-mono">{{ $payloadVersion }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-[color:var(--color-text-muted)]">Endpoint</p>
                <p class="mt-1 break-all font-mono text-xs">GET /api/v1/extension/chrome/block-rules</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-[color:var(--color-text-muted)]">Propagación</p>
                <p class="mt-1">Cada extensión revisa las reglas al abrirse el navegador y cada 30 minutos.</p>
            </div>
        </div>
    </x-ui.card>

    @if($canManage && ! $showForm)
        <div class="flex justify-end">
            <x-ui.button type="button" wire:click="create">Nueva regla</x-ui.button>
        </div>
    @endif

    @if($showForm)
        <x-ui.card :title="$editingId ? 'Editar regla' : 'Nueva regla'">
            <div class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input label="Nombre" wire:model="label" :error="$errors->first('label')" placeholder="Service Order" required />
                    <x-ui.textarea label="Destinos" wire:model="hostPatterns" rows="4" :error="$errors->first('hostPatterns')"
                        description="Uno por línea. Puedes pegar la URL completa (https://sysprovincia2.shalomcontrol.com/ordenservicio/listar) o solo el dominio, que heredará la ruta de abajo. Admite comodín: *.shalomcontrol.com"
                        required />
                    <x-ui.input label="Ruta por defecto" wire:model="pathPattern" :error="$errors->first('pathPattern')"
                        description="La usan los destinos que no traen ruta propia. /* bloquea el dominio entero." required />
                    <x-ui.input label="Zona horaria" wire:model="timezone" :error="$errors->first('timezone')" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <span class="mb-2 block text-sm font-medium">Las horas configuradas son…</span>
                        <div class="grid gap-2" role="radiogroup" aria-label="Modo de horario">
                            <button type="button" wire:click="$set('windowMode', 'allowed')"
                                class="focus-ring rounded-[var(--radius-control)] px-3 py-2 text-left text-sm ring-1 ring-inset {{ $windowMode === 'allowed' ? 'bg-[color:var(--color-primary)] text-white ring-[color:var(--color-primary)]' : 'bg-[color:var(--color-surface)] ring-[color:var(--color-border)]' }}"
                                role="radio" aria-checked="{{ $windowMode === 'allowed' ? 'true' : 'false' }}">
                                Horario permitido (fuera de él se bloquea)
                            </button>
                            <button type="button" wire:click="$set('windowMode', 'blocked')"
                                class="focus-ring rounded-[var(--radius-control)] px-3 py-2 text-left text-sm ring-1 ring-inset {{ $windowMode === 'blocked' ? 'bg-[color:var(--color-primary)] text-white ring-[color:var(--color-primary)]' : 'bg-[color:var(--color-surface)] ring-[color:var(--color-border)]' }}"
                                role="radio" aria-checked="{{ $windowMode === 'blocked' ? 'true' : 'false' }}">
                                Horario bloqueado (fuera de él se permite)
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-[color:var(--color-text-muted)]">
                            El comportamiento actual de la extensión es «horario permitido»: 08:00–20:05 se trabaja, el resto queda bloqueado.
                        </p>
                    </div>
                    <div class="flex items-end">
                        <x-ui.toggle wire:model="isActive" label="Regla activa" description="Si se desactiva, la extensión deja de aplicarla." />
                    </div>
                </div>

                <div class="space-y-3 rounded-[var(--radius-control)] p-4 ring-1 ring-inset ring-[color:var(--color-border)]">
                    <div class="flex flex-wrap items-end gap-3">
                        <x-ui.input type="time" label="Desde" wire:model="bulkStart" wrapperClass="w-32" />
                        <x-ui.input type="time" label="Hasta" wire:model="bulkEnd" wrapperClass="w-32" />
                        <x-ui.button type="button" size="sm" variant="secondary" wire:click="applyRange('monday-saturday')">Lunes a sábado</x-ui.button>
                        <x-ui.button type="button" size="sm" variant="secondary" wire:click="applyRange('weekdays')">Lunes a viernes</x-ui.button>
                        <x-ui.button type="button" size="sm" variant="secondary" wire:click="applyRange('sunday')">Domingo</x-ui.button>
                        <x-ui.button type="button" size="sm" variant="secondary" wire:click="applyRange('all')">Todos los días</x-ui.button>
                        <x-ui.button type="button" size="sm" variant="ghost" wire:click="clearSchedule">Limpiar</x-ui.button>
                    </div>

                    @error('schedule')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror

                    <div class="grid gap-2">
                        @foreach($days as $dayNumber => $dayLabel)
                            <div class="flex flex-wrap items-center gap-3 rounded-[var(--radius-control)] bg-white/5 px-3 py-2">
                                <label class="flex w-40 items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model.live="schedule.{{ $dayNumber }}.enabled"
                                        class="focus-ring h-4 w-4 rounded border-0 ring-1 ring-inset ring-[color:var(--color-border)]" />
                                    <span>{{ $dayLabel }}</span>
                                </label>
                                <input type="time" wire:model="schedule.{{ $dayNumber }}.start"
                                    @disabled(! ($schedule[$dayNumber]['enabled'] ?? false))
                                    class="focus-ring rounded-[var(--radius-control)] border-0 bg-[color:var(--color-surface)] px-3 py-1.5 text-sm ring-1 ring-inset ring-[color:var(--color-border)] disabled:opacity-40" />
                                <span class="text-xs text-[color:var(--color-text-muted)]">a</span>
                                <input type="time" wire:model="schedule.{{ $dayNumber }}.end"
                                    @disabled(! ($schedule[$dayNumber]['enabled'] ?? false))
                                    class="focus-ring rounded-[var(--radius-control)] border-0 bg-[color:var(--color-surface)] px-3 py-1.5 text-sm ring-1 ring-inset ring-[color:var(--color-border)] disabled:opacity-40" />
                                @if(! ($schedule[$dayNumber]['enabled'] ?? false))
                                    <span class="text-xs text-[color:var(--color-text-muted)]">
                                        {{ $windowMode === 'allowed' ? 'Bloqueado todo el día' : 'Sin bloqueo' }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <x-ui.textarea label="Notas" wire:model="notes" rows="2" :error="$errors->first('notes')" />

                <div class="flex gap-3">
                    <x-ui.button type="button" wire:click="save" loading-target="save">Guardar regla</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card title="Reglas configuradas">
        @if($rules->isEmpty())
            <x-ui.empty-state title="Sin reglas" description="Ninguna extensión aplicará bloqueo horario hasta que crees una regla." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-[color:var(--color-text-muted)]">
                        <tr>
                            <th class="px-3 py-2">Regla</th>
                            <th class="px-3 py-2">Destino</th>
                            <th class="px-3 py-2">Horario</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($rules as $rule)
                            <tr>
                                <td class="px-3 py-3 align-top">
                                    <p class="font-medium">{{ $rule->label }}</p>
                                    <p class="text-xs text-[color:var(--color-text-muted)]">{{ $rule->timezone }}</p>
                                </td>
                                <td class="px-3 py-3 align-top font-mono text-xs">
                                    <ul class="space-y-0.5">
                                        @foreach($rule->destinations() as $destination)
                                            <li>{{ $destination['host_pattern'] }}{{ $destination['path_pattern'] }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <p class="mb-1 text-xs text-[color:var(--color-text-muted)]">
                                        {{ $rule->window_mode === 'allowed' ? 'Permitido en:' : 'Bloqueado en:' }}
                                    </p>
                                    <ul class="space-y-0.5">
                                        @foreach($rule->windows as $window)
                                            <li class="text-xs">
                                                {{ $days[$window->day_of_week] ?? $window->day_of_week }}
                                                {{ substr((string) $window->start_time, 0, 5) }}–{{ substr((string) $window->end_time, 0, 5) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <x-ui.badge :tone="$rule->is_active ? 'success' : 'neutral'">
                                        {{ $rule->is_active ? 'Activa' : 'Inactiva' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-3 py-3 text-right align-top">
                                    @if($canManage)
                                        <div class="inline-flex gap-2">
                                            <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $rule->id }})">Editar</x-ui.button>
                                            <x-ui.button type="button" size="sm" variant="secondary" wire:click="toggleActive({{ $rule->id }})">
                                                {{ $rule->is_active ? 'Desactivar' : 'Activar' }}
                                            </x-ui.button>
                                            <x-ui.button type="button" size="sm" variant="danger" wire:click="delete({{ $rule->id }})"
                                                wire:confirm="¿Eliminar la regla «{{ $rule->label }}»?">Eliminar</x-ui.button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
