<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    {{-- Phase 8 — PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CleanUx">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CleanUx') }} | Nettoyage simple, suivi et professionnel</title>

    <meta name="description" content="Réservez un service de nettoyage professionnel, suivez l’employé en route, validez la mission par code et laissez votre feedback.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="font-sans antialiased bg-slate-50 text-slate-900">
    <div class="min-h-screen">
        <header class="sticky top-0 z-50 border-b border-white/60 bg-white/85 backdrop-blur-xl">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-600 text-white font-black">
                        C
                    </div>
                    <div>
                        <p class="text-lg font-black tracking-tight text-slate-900">
                            {{ config('app.name', 'CleanUx') }}
                        </p>
                        <p class="hidden text-xs text-slate-500 sm:block">Nettoyage connecté & professionnel</p>
                    </div>
                </a>

                <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                    <a href="{{ route('home') }}#services" class="hover:text-blue-700">Services</a>
                    <a href="{{ route('home') }}#fonctionnement" class="hover:text-blue-700">Fonctionnement</a>
                    <a href="{{ route('home') }}#b2b" class="hover:text-blue-700">Entreprises</a>
                    <a href="{{ route('home') }}#premium" class="hover:text-blue-700">Premium</a>
                </nav>

                <div class="flex items-center gap-2">
                    @auth
                    <a href="{{ route('dashboard') }}"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Dashboard
                    </a>
                    @else
                    <a href="{{ route('login') }}"
                        class="hidden rounded-xl px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex">
                        Connexion
                    </a>

                    <a href="{{ route('booking.create') }}"
                        class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                        Réserver
                    </a>
                    @endauth
                </div>
            </div>
        </header>

        {{ $slot }}
    </div>

    @livewireScripts
    @stack('scripts')
</body>

</html>