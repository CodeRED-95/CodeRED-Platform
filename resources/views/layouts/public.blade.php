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
<body class="platform-public public-token-screen min-h-dvh bg-[color:var(--color-background)] text-[color:var(--color-text-primary)]">
    <div class="public-token-ornament" aria-hidden="true">赤</div>
    <main class="mx-auto flex min-h-dvh w-full max-w-4xl items-center px-4 py-10 sm:px-6 lg:py-14">
        <div class="public-token-frame w-full">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
