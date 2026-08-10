<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - {{ $pageTitle ?? 'Crear cuenta' }}</title>
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
                <h1 class="font-display text-5xl font-semibold tracking-tight">Crea tu cuenta de acceso</h1>
                <p class="max-w-lg text-base text-[color:var(--color-text-secondary)]">El registro público asigna automáticamente el rol <strong>viewer</strong> para que puedas consultar agencias y sincronizar tu extensión sin permisos administrativos.</p>
            </div>
        </div>
        <div class="grid max-w-2xl grid-cols-3 gap-4">
            <x-ui.stat-card label="Acceso" value="Viewer" tone="brand" />
            <x-ui.stat-card label="Agencias" value="Mapa" tone="ivory" />
            <x-ui.stat-card label="Sync" value="Shalom" tone="info" />
        </div>
    </section>
    <section class="flex items-center justify-center px-4 py-8 lg:px-10">
        <form method="POST" action="{{ route('register.store') }}" class="w-full max-w-md rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 p-6 shadow-2xl backdrop-blur">
            @csrf
            <div class="mb-8 space-y-3 lg:hidden">
                <x-ui.logo variant="full" class="h-14" />
                <p class="text-sm text-[color:var(--color-text-secondary)]">Registro público de viewer</p>
            </div>
            <h2 class="font-display text-3xl font-semibold tracking-tight">Crear cuenta</h2>
            <p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">Tu cuenta se creará con acceso de consulta y sincronización propia.</p>

            @if ($errors->any())
                <x-ui.alert tone="danger" class="mt-6 text-sm">
                    <p class="font-medium text-[color:var(--color-danger)]">Revisa los campos marcados.</p>
                </x-ui.alert>
            @endif

            <div class="mt-6 space-y-4">
                <x-ui.input type="text" id="name" name="name" label="Nombre" autocomplete="name" :value="old('name')" :error="$errors->first('name')" />
                <x-ui.input type="email" id="email" name="email" label="Correo electrónico" autocomplete="email" :value="old('email')" :error="$errors->first('email')" />
                <x-ui.input type="password" id="password" name="password" label="Contraseña" autocomplete="new-password" :error="$errors->first('password')" />
                <x-ui.input type="password" id="password_confirmation" name="password_confirmation" label="Confirmar contraseña" autocomplete="new-password" :error="$errors->first('password_confirmation')" />
            </div>
            <p class="mt-4 text-xs text-[color:var(--color-text-secondary)]">Al registrarte aceptas usar tu cuenta solo para consulta y sincronización propia.</p>
            <div class="mt-6 flex flex-col gap-3">
                <x-ui.button type="submit" variant="primary" class="w-full">Crear cuenta</x-ui.button>
                <x-ui.button href="{{ route('login') }}" variant="outline" class="w-full">Ya tengo cuenta</x-ui.button>
            </div>
        </form>
    </section>
</div>
</body>
</html>
