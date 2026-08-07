<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    {{-- Phase 8 — PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Brio">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ Auth::user()->currentOrganization?->name ?? 'Brio' }} — Espace prestataire</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-slate-900 text-slate-100 antialiased">

    {{-- ── Topbar ── --}}
    @unless($embedded ?? false)
    <nav data-chrome="primary-nav" aria-label="Navigation principale" class="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-slate-700 bg-slate-900/95 px-4 backdrop-blur">
        <div class="flex items-center gap-3">
            <a href="{{ route('provider-company.dashboard') }}" class="text-lg font-black text-white">
                Brio <span class="text-amber-400">Pro</span>
            </a>
            <div class="hidden sm:flex items-center gap-1">
                {{-- Les six liens vivaient en dur ici. Ils viennent désormais de
                     `config/modules.php`, comme ceux de la navbar et de la page Modules. --}}
                @foreach (\App\Support\Navigation\ModuleCatalogue::principaux('provider-company') as $link)
                <a href="{{ route($link['route']) }}"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition
                           {{ request()->routeIs($link['route'])
                               ? 'bg-slate-700 text-white font-medium'
                               : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' }}">
                    <span class="text-sm">{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                </a>
                @endforeach

                {{-- La porte vers tout le reste — ce bandeau est la seule surface permanente de
                     l'espace société prestataire. --}}
                @if (\Illuminate\Support\Facades\Route::has('provider-company.modules'))
                    <a href="{{ route('provider-company.modules') }}"
                        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition
                               {{ request()->routeIs('provider-company.modules')
                                   ? 'bg-slate-700 text-white font-medium'
                                   : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' }}">
                        <span class="text-sm">🧩</span>
                        <span>Modules</span>
                    </a>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            {{-- Assistant --}}
            <div class="hidden md:block">
                <livewire:chatbot.assistant-widget />
            </div>
            <a href="{{ route('profile.show') }}"
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-800 transition">
                <img src="{{ Auth::user()->profile_photo_url }}"
                    class="h-7 w-7 rounded-full object-cover border border-slate-600">
                <span class="hidden sm:block text-sm text-slate-300">{{ str(Auth::user()->name)->before(' ') }}</span>
            </a>
        </div>
    </nav>
    @endunless

    {{-- ── Contenu ── --}}
    <main>
        {{ $slot }}
    </main>

    @livewireScripts
    <x-mobile-bottom-nav />
    <x-pwa-install-prompt />
</body>

</html>