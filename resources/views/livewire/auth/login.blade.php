<div class="grid min-h-screen lg:grid-cols-[1.2fr_0.8fr]">
    <section class="hidden flex-col justify-between border-r border-white/10 p-8 lg:flex">
        <div class="max-w-xl space-y-6">
            <x-ui.logo variant="full" class="h-16" />
            <div class="space-y-3">
                <p class="text-sm uppercase tracking-[0.28em] text-[color:var(--color-brand-light)]">CodeRED Platform</p>
                <h1 class="font-display text-5xl font-semibold tracking-tight">Plataforma modular de administración</h1>
                <p class="max-w-lg text-base text-[color:var(--color-text-secondary)]">Operamos agencias, importaciones y servicios con una interfaz empresarial clara, sobria y pensada para equipos internos.</p>
            </div>
        </div>
        <div class="grid max-w-2xl grid-cols-3 gap-4">
            <x-ui.stat-card label="Seguridad" value="Auth" tone="brand" />
            <x-ui.stat-card label="Datos" value="PGSQL" tone="ivory" />
            <x-ui.stat-card label="Colas" value="Redis" tone="info" />
        </div>
    </section>

    <section class="flex items-center justify-center px-4 py-8 lg:px-10">
        <form wire:submit.prevent="submit" class="w-full max-w-md rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 p-6 shadow-2xl backdrop-blur">
            <div class="mb-8 space-y-3 lg:hidden">
                <x-ui.logo variant="full" class="h-14" />
                <p class="text-sm text-[color:var(--color-text-secondary)]">Plataforma modular de administración</p>
            </div>
            <h2 class="font-display text-3xl font-semibold tracking-tight">Iniciar sesión</h2>
            <p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">Acceso administrativo seguro a CodeRED Platform.</p>

            <div class="mt-6 space-y-4">
                <x-ui.input wire:model="email" type="email" label="Correo" autocomplete="email" placeholder="admin@codered.local" :error="$errors->first('email')" />
                <div x-data="{ show: false }" class="space-y-1">
                    <label class="block text-sm font-medium text-[color:var(--color-text-primary)]">Contraseña</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" wire:model="password" autocomplete="current-password" class="w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-4 py-3 pr-12 text-sm text-[color:var(--color-text-primary)] placeholder:text-[color:var(--color-text-muted)] focus-ring">
                        <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 px-4 text-xs text-[color:var(--color-text-secondary)]">Ver</button>
                    </div>
                    @error('password') <span class="text-sm text-[color:var(--color-danger)]">{{ $message }}</span> @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-[color:var(--color-text-secondary)]">
                    <input wire:model="remember" type="checkbox" class="rounded border-[color:var(--color-border)] bg-[color:var(--color-surface)] text-[color:var(--color-brand)] focus-ring">
                    Recordarme
                </label>
            </div>

            <x-ui.button type="submit" variant="primary" class="mt-6 w-full">Entrar</x-ui.button>
        </form>
    </section>
</div>
