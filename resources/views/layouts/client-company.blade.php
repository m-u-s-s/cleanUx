<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    {{-- Phase 8 — PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <x-brand.head space="client" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ Auth::user()->currentOrganization?->name ?? 'Brio' }} — Espace client</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

{{--
    Meme fond que `layouts/app.blade.php` : le `bg-slate-50/30` est semi-transparent et laisse
    passer le degrade defini dans `resources/css/base.css`. Un `bg-slate-50` opaque le masquait,
    et donnait un gris plat que la console d'administration n'a pas.
--}}
<body class="font-sans antialiased text-slate-800 bg-slate-50/30 selection:bg-brand-100 selection:text-brand-900">

    {{-- ── Topbar ── --}}
    @unless($embedded ?? false)
    <nav data-chrome="primary-nav" aria-label="Navigation principale" class="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-slate-100 bg-white/95 px-4 backdrop-blur">
        <div class="flex items-center gap-3">
            <a href="{{ route('client-company.dashboard') }}"
                class="flex items-center gap-2 text-lg font-black text-slate-900">
                <x-brand.logo space="client" :size="32" />
                {{-- La marque était coupée par une balise : « Clean<span>Ux</span> ». Le
                     renommage global ne pouvait pas la voir. --}}
                Br<span class="text-sky-600">io</span>
            </a>
            <div class="hidden sm:flex items-center gap-1">
                {{-- Les onze liens vivaient en dur ici. Ils viennent désormais de
                     `config/modules.php`, comme ceux de la navbar et de la page Modules :
                     `ModuleCatalogue` retire déjà les routes absentes. --}}
                @foreach (\App\Support\Navigation\ModuleCatalogue::principaux('client-company') as $link)
                <a href="{{ route($link['route']) }}"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition
                           {{ request()->routeIs($link['route'])
                               ? 'bg-slate-100 text-slate-900 font-semibold'
                               : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                    <span>{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                </a>
                @endforeach

                {{-- La porte vers tout le reste : ce bandeau est la seule surface permanente de
                     l'espace société, et sept de ses modules n'y figurent pas. --}}
                @if (\Illuminate\Support\Facades\Route::has('client-company.modules'))
                    <a href="{{ route('client-company.modules') }}"
                        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition
                               {{ request()->routeIs('client-company.modules')
                                   ? 'bg-slate-100 text-slate-900 font-semibold'
                                   : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        <span>🧩</span>
                        <span>Modules</span>
                    </a>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('client-company.bookings.create') }}"
                class="hidden sm:flex items-center gap-1.5 rounded-xl bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">
                ⚡ Demande rapide
            </a>

            @if (\Illuminate\Support\Facades\Route::has('client.dashboard'))
                <a href="{{ route('client.dashboard') }}"
                    class="hidden sm:flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50"
                    title="Revenir à mon espace personnel">
                    👤 Mon espace perso
                </a>
            @endif

            {{-- Assistant --}}
            <livewire:chatbot.assistant-widget />

            <a href="{{ route('profile.show') }}"
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 transition">
                <img src="{{ Auth::user()->profile_photo_url }}"
                    class="h-7 w-7 rounded-full object-cover border border-slate-200">
                <div class="hidden sm:block text-right">
                    <p class="text-xs font-semibold text-slate-800">{{ str(Auth::user()->name)->before(' ') }}</p>
                    <p class="text-[10px] text-sky-600">{{ Auth::user()->membershipIn()?->roleLabel() }}</p>
                </div>
            </a>
        </div>
    </nav>
    @endunless

    {{--
        Le contenu est enveloppe dans `brio-page`, comme celui de `layouts/app.blade.php` : meme
        largeur maximale, meme rythme vertical. Les ecrans portaient chacun leur propre
        `min-h-screen … p-6`, d'ou des marges qui ne tombaient pas au meme endroit que sur l'admin.
    --}}
    <main class="px-3 py-5 sm:px-6 lg:px-8">
        <div class="brio-page animate-fade-in">
            {{ $slot }}
        </div>
    </main>

    @livewireScripts
</body>

</html>