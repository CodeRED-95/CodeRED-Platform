<x-layouts.auth page-title="{{ $pageTitle ?? 'Iniciar sesión' }}">
    <x-slot:promo>
        <div class="relative mx-auto w-full max-w-[clamp(18rem,24vw,29rem)]">
            <x-ui.logo variant="square" class="login-ceremonial-logo w-full h-auto drop-shadow-[0_0_30px_rgba(225,29,72,0.18)]" />
        </div>

        <div class="mt-6 space-y-3 xl:mt-7">
            <h1 class="font-display text-[clamp(2.5rem,3vw,3.8rem)] font-semibold leading-[0.96] tracking-[-0.03em]">Tu operación, más clara.</h1>
            <p class="max-w-xl text-[clamp(1rem,1.05vw,1.125rem)] leading-[1.55] text-[color:var(--color-text-secondary)]">
                CodeRED Platform concentra las herramientas que tu equipo usa para administrar, consultar y conectar el ecosistema.
            </p>
        </div>

        <dl class="mt-8 grid max-w-2xl border-y border-[color:var(--color-border-subtle)] divide-y divide-[color:var(--color-border-subtle)] text-sm xl:mt-10">
            <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr] sm:gap-4">
                <dt class="font-semibold text-[color:var(--color-brand-light)]">Administración</dt>
                <dd class="text-[color:var(--color-text-secondary)]">Agencias, usuarios y permisos en un solo lugar.</dd>
            </div>
            <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr] sm:gap-4">
                <dt class="font-semibold text-[color:var(--color-brand-light)]">Consulta</dt>
                <dd class="text-[color:var(--color-text-secondary)]">Herramientas DNI y RUC para el trabajo diario.</dd>
            </div>
            <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr] sm:gap-4">
                <dt class="font-semibold text-[color:var(--color-brand-light)]">Conexión</dt>
                <dd class="text-[color:var(--color-text-secondary)]">Tokens, servicios e integraciones del ecosistema.</dd>
            </div>
        </div>

        <footer class="pt-6 text-sm text-[color:var(--color-text-secondary)] xl:pt-7">
            © 2026 CodeRED Platform. Todos los derechos reservados.
        </footer>
    </x-slot:promo>

    <form
        method="POST"
        action="{{ route('login.store') }}"
        class="login-form w-full rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 px-5 py-6 shadow-2xl backdrop-blur sm:px-6 sm:py-7 lg:px-8 lg:py-7"
    >
        @csrf
        @if ($redirect ?? null)
            <input type="hidden" name="redirect" value="{{ $redirect }}">
        @endif

        <div class="mx-auto mb-6 flex flex-col items-center gap-4 text-center lg:mb-6">
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
        @if (session('status'))
            <x-ui.alert tone="success" class="mb-6 text-sm">{{ session('status') }}</x-ui.alert>
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

        <div class="mt-6 space-y-4">
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

        <p class="mt-5 text-center text-sm text-[color:var(--color-text-secondary)] lg:hidden">
            Plataforma modular • Segura • Confiable
        </p>
    </form>
</x-layouts.auth>
