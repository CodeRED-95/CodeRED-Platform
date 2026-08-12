<x-layouts.auth page-title="{{ $pageTitle ?? 'Iniciar sesión' }}">
    <x-slot:promo>
        <div class="relative inline-flex">
            <div class="pointer-events-none absolute inset-0 -z-10 translate-y-2 scale-110 rounded-full bg-[radial-gradient(circle_at_center,rgba(225,29,72,0.34),rgba(225,29,72,0)_65%)] blur-2xl"></div>
            <x-ui.logo variant="full" class="h-20 w-auto drop-shadow-[0_0_30px_rgba(225,29,72,0.18)]" />
        </div>

        <div class="mt-8 space-y-3">
            <p class="text-sm uppercase tracking-[0.32em] text-[color:var(--color-brand-light)]">CodeRED Platform</p>
            <h1 class="font-display text-5xl font-semibold tracking-tight">Tu centro de operaciones</h1>
            <p class="max-w-xl text-lg leading-8 text-[color:var(--color-text-secondary)]">
                Administra agencias, RUC, integraciones y automatizaciones
                desde una plataforma modular, segura y diseñada para
                potenciar a tu equipo.
            </p>
        </div>

        <div class="grid max-w-2xl gap-4 pt-8">
            <div class="flex items-start gap-4 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-white/3 p-4 shadow-[var(--shadow-card)] backdrop-blur">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)]">
                    <x-ui.icon name="database" class="size-5" />
                </div>
                <div>
                    <p class="text-base font-semibold text-white">Administración centralizada</p>
                    <p class="mt-1 text-sm leading-6 text-[color:var(--color-text-secondary)]">Gestiona agencias, usuarios y permisos en un solo lugar.</p>
                </div>
            </div>

            <div class="flex items-start gap-4 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-white/3 p-4 shadow-[var(--shadow-card)] backdrop-blur">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)]">
                    <x-ui.icon name="shield" class="size-5" />
                </div>
                <div>
                    <p class="text-base font-semibold text-white">Integraciones seguras</p>
                    <p class="mt-1 text-sm leading-6 text-[color:var(--color-text-secondary)]">Conecta n8n, servicios y herramientas con total confianza.</p>
                </div>
            </div>

            <div class="flex items-start gap-4 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-white/3 p-4 shadow-[var(--shadow-card)] backdrop-blur">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)]">
                    <x-ui.icon name="refresh" class="size-5" />
                </div>
                <div>
                    <p class="text-base font-semibold text-white">Datos y automatización</p>
                    <p class="mt-1 text-sm leading-6 text-[color:var(--color-text-secondary)]">Procesos inteligentes para decisiones más rápidas.</p>
                </div>
            </div>
        </div>

        <footer class="pt-10 text-sm text-[color:var(--color-text-secondary)]">
            © 2026 CodeRED Platform. Todos los derechos reservados.
        </footer>
    </x-slot:promo>

    <form
        method="POST"
        action="{{ route('login.store') }}"
        class="w-full rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 px-5 py-6 shadow-2xl backdrop-blur sm:px-6 sm:py-7 lg:px-8 lg:py-8"
    >
        @csrf

        <div class="mx-auto mb-7 flex flex-col items-center gap-4 text-center">
            <div class="flex size-16 items-center justify-center rounded-[var(--radius-card)] border border-white/10 bg-white/5 shadow-[0_0_0_1px_rgba(255,255,255,0.03)]">
                <x-ui.logo variant="symbol" class="h-8 w-8" />
            </div>
            <div class="space-y-2">
                <h2 class="font-display text-3xl font-semibold tracking-tight">Iniciar sesión</h2>
                <p class="text-sm text-[color:var(--color-text-secondary)]">Acceso administrativo seguro a CodeRED Platform.</p>
            </div>
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-6 text-sm">
                <p class="font-medium text-[color:var(--color-danger)]">Revisa los campos marcados.</p>
            </x-ui.alert>
        @endif

        <div class="space-y-4">
            <x-ui.input
                type="email"
                id="email"
                name="email"
                label="Correo electrónico"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                placeholder="admin@codered.local"
                :value="old('email')"
                :error="$errors->first('email')"
                required
            >
                <x-slot:icon>
                    <x-ui.icon name="inbox" class="size-5" />
                </x-slot:icon>
            </x-ui.input>

            <div x-data="{ showPassword: false }">
                <x-ui.input
                    type="password"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    id="password"
                    name="password"
                    label="Contraseña"
                    autocomplete="current-password"
                    :error="$errors->first('password')"
                    required
                >
                    <x-slot:icon>
                        <x-ui.icon name="shield" class="size-5" />
                    </x-slot:icon>
                    <x-slot:suffix>
                        <button
                            type="button"
                            x-on:click="showPassword = ! showPassword"
                            aria-label="Mostrar contraseña"
                            class="rounded-md p-2 text-[color:var(--color-text-secondary)] transition hover:bg-white/5 hover:text-white focus-ring"
                        >
                            <span class="sr-only" x-text="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"></span>
                            <x-ui.icon name="eye" class="size-5" />
                        </button>
                    </x-slot:suffix>
                </x-ui.input>
            </div>

            <div class="flex items-center justify-between gap-4">
                <x-ui.checkbox name="remember" value="1" :checked="(bool) old('remember')">Recordarme</x-ui.checkbox>
                @if (Route::has('password.request'))
                    <x-ui.button href="{{ route('password.request') }}" variant="link" class="text-sm font-medium">¿Olvidaste tu contraseña?</x-ui.button>
                @endif
            </div>
        </div>

        <div class="mt-7 space-y-4">
            <x-ui.button type="submit" variant="primary" class="w-full bg-[color:var(--color-brand)] py-3.5 text-base font-semibold">
                Entrar
            </x-ui.button>

            <div class="flex items-center gap-4 text-[color:var(--color-text-muted)]">
                <span class="h-px flex-1 bg-white/10"></span>
                <span class="text-xs uppercase tracking-[0.28em]">o</span>
                <span class="h-px flex-1 bg-white/10"></span>
            </div>

            <x-ui.button href="{{ route('register') }}" variant="outline" class="w-full py-3.5 text-base font-semibold">
                Crear cuenta
            </x-ui.button>
        </div>

        <p class="mt-6 text-center text-sm text-[color:var(--color-text-secondary)] lg:hidden">
            Plataforma modular • Segura • Confiable
        </p>
    </form>
</x-layouts.auth>
