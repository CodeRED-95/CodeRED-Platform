<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('pageTitle', 'CodeRED Platform')</title>
    <meta name="description" content="@yield('metaDescription', 'Plataforma de gestión de agencias y logística.')">
    <link rel="canonical" href="@yield('canonical', url()->current())" />

    <!-- Open Graph -->
    <meta property="og:title" content="@yield('ogTitle', 'CodeRED Platform')" />
    <meta property="og:description" content="@yield('ogDescription', 'Plataforma de gestión de agencias y logística.')" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="@yield('ogUrl', url()->current())" />
    <meta property="og:image" content="{{ asset('images/branding/og-image.png') }}" />

    <link rel="icon" href="{{ asset('images/branding/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-[color:var(--color-background)] text-[color:var(--color-text-primary)]">
    <main class="mx-auto flex min-h-dvh w-full max-w-3xl items-center px-4 py-10">
        {{ $slot }}
    </main>
</body>
</html>
