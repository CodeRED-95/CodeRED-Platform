@props([
    'pageTitle' => null,
])

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - {{ $pageTitle ?? 'CodeRED Platform' }}</title>
    <link rel="icon" href="{{ asset('images/branding/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh overflow-x-hidden code-red-shell text-[color:var(--color-text-primary)]">
<main class="grid min-h-dvh w-full grid-cols-1 overflow-hidden lg:grid-cols-[minmax(0,1.12fr)_minmax(0,0.88fr)]">
    <section
        class="relative hidden min-h-dvh min-w-0 overflow-hidden border-r border-white/10 px-6 py-8 lg:flex xl:px-8 xl:py-10"
    >
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute left-0 top-0 h-[38rem] w-[38rem] -translate-x-1/2 -translate-y-1/3 rounded-full bg-[radial-gradient(circle_at_center,rgba(225,29,72,0.20),rgba(225,29,72,0)_70%)] blur-3xl"></div>
            <div class="absolute right-16 top-28 h-56 w-56 rounded-full bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.06),rgba(255,255,255,0)_72%)] blur-3xl"></div>
            <div class="absolute inset-x-0 top-1/3 h-px bg-gradient-to-r from-transparent via-[color:var(--color-brand)]/50 to-transparent opacity-50"></div>
        </div>

        <div class="relative z-10 flex w-full min-w-0 flex-col justify-between">
            <div class="min-w-0">
                {{ $promo ?? '' }}
            </div>
        </div>
    </section>

    <section class="flex min-h-dvh min-w-0 items-center justify-center px-4 py-6 sm:px-6 lg:px-8">
        <div class="w-full max-w-[30rem] min-w-0">
            {{ $slot }}
        </div>
    </section>
</main>
</body>
</html>
