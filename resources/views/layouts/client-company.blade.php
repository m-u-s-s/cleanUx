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
    <title>{{ Auth::user()->currentOrganization?->name ?? 'CleanUx' }} — Espace client</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-slate-50 text-slate-900 antialiased">

    {{-- ── Topbar ── --}}
    <nav class="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur">
        <div class="flex items-center gap-3">
            <a href="{{ route('client-company.dashboard') }}"
                class="text-lg font-black text-slate-900">
                Clean<span class="text-purple-600">Ux</span>
            </a>
            <div class="hidden sm:flex items-center gap-1">
                @foreach ([
                ['route' => 'client-company.dashboard', 'label' => 'Accueil', 'icon' => '🏠'],
                ['route' => 'client-company.sites', 'label' => 'Mes locaux', 'icon' => '📍'],
                ['route' => 'client-company.bookings.index','label' => 'Réservations', 'icon' => '📅'],
                ['route' => 'client-company.members', 'label' => 'Membres', 'icon' => '👥'],
                ['route' => 'client-company.billing', 'label' => 'Facturation', 'icon' => '🧾'],
                ] as $link)
                <a href="{{ route($link['route']) }}"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition
                           {{ request()->routeIs($link['route'])
                               ? 'bg-purple-50 text-purple-700 font-semibold'
                               : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                    <span>{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                </a>
                @endforeach
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('client-company.bookings.create') }}"
                class="hidden sm:flex items-center gap-1.5 rounded-xl bg-purple-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-purple-700">
                ⚡ Demande rapide
            </a>

            {{-- Assistant --}}
            <livewire:chatbot.assistant-widget />

            <a href="{{ route('profile.show') }}"
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 transition">
                <img src="{{ Auth::user()->profile_photo_url }}"
                    class="h-7 w-7 rounded-full object-cover border border-slate-200">
                <div class="hidden sm:block text-right">
                    <p class="text-xs font-semibold text-slate-800">{{ str(Auth::user()->name)->before(' ') }}</p>
                    <p class="text-[10px] text-purple-600">{{ Auth::user()->membershipIn()?->roleLabel() }}</p>
                </div>
            </a>
        </div>
    </nav>

    {{-- ── Contenu ── --}}
    <main>
        {{ $slot }}
    </main>

    @livewireScripts
</body>

</html>