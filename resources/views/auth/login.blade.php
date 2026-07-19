<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - {{ $pageTitle ?? 'Iniciar sesión' }}</title>
    <link rel="icon" href="{{ asset('images/branding/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full code-red-shell text-[color:var(--color-text-primary)]">
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
        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="w-full max-w-md rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 p-6 shadow-2xl backdrop-blur"
        >
            @csrf
            <div class="mb-8 space-y-3 lg:hidden">
                <x-ui.logo variant="full" class="h-14" />
                <p class="text-sm text-[color:var(--color-text-secondary)]">Plataforma modular de administración</p>
            </div>
            <h2 class="font-display text-3xl font-semibold tracking-tight">Iniciar sesión</h2>
            <p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">Acceso administrativo seguro a CodeRED Platform.</p>

            @if ($errors->any())
                <x-ui.alert tone="danger" class="mt-6 text-sm">
                    <p class="font-medium text-[color:var(--color-danger)]">Revisa los campos marcados.</p>
                </x-ui.alert>
            @endif

            <div class="mt-6 space-y-4">
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
                />
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
                        <x-slot:suffix>
                        <button
                            type="button"
                            x-on:click="showPassword = ! showPassword"
                            x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            class="rounded-md px-2 py-1 text-xs text-[color:var(--color-text-secondary)] transition hover:bg-white/5 hover:text-white focus-ring"
                            x-text="showPassword ? 'Ocultar' : 'Ver'"
                        ></button>
                        </x-slot:suffix>
                    </x-ui.input>
                </div>
                <x-ui.checkbox name="remember" value="1" :checked="(bool) old('remember')">Recordarme</x-ui.checkbox>
            </div>

            <x-ui.button type="submit" variant="primary" class="mt-6 w-full">
                Entrar
            </x-ui.button>
        </form>
    </section>
</div>
</body>
</html>
