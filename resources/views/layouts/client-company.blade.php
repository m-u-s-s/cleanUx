<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <x-theme-amorce />
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
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <a href="{{ route('client-company.dashboard') }}"
                class="flex flex-shrink-0 items-center gap-2 text-lg font-black text-slate-900">
                <x-brand.logo space="client" :size="32" />
                {{-- La marque était coupée par une balise : « Clean<span>Ux</span> ». Le
                     renommage global ne pouvait pas la voir. --}}
                Br<span class="text-sky-600">io</span>
            </a>
            <div class="hidden min-w-0 items-center gap-1 overflow-x-auto sm:flex [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                {{-- Les onze liens vivaient en dur ici. Ils viennent désormais de
                     `config/modules.php`, comme ceux de la navbar et de la page Modules :
                     `ModuleCatalogue` retire déjà les routes absentes. --}}
                @foreach (\App\Support\Navigation\ModuleCatalogue::principaux('client-company') as $link)
                <a href="{{ route($link['route']) }}"
                    class="flex flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm transition
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
                        class="flex flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm transition
                               {{ request()->routeIs('client-company.modules')
                                   ? 'bg-slate-100 text-slate-900 font-semibold'
                                   : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        <span>🧩</span>
                        <span>Modules</span>
                    </a>
                @endif
            </div>
        </div>
        <div class="flex flex-shrink-0 items-center gap-2">
            <a href="{{ route('client-company.bookings.create') }}"
                class="hidden flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 sm:flex"
                title="Demande rapide" aria-label="Demande rapide">
                ⚡ <span class="hidden 2xl:inline">Demande rapide</span>
            </a>

            @if (\Illuminate\Support\Facades\Route::has('client.dashboard'))
                <a href="{{ route('client.dashboard') }}"
                    class="hidden flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50 sm:flex"
                    title="Revenir à mon espace personnel" aria-label="Revenir à mon espace personnel">
                    👤 <span class="hidden 2xl:inline">Mon espace perso</span>
                </a>
            @endif

            {{-- Le theme, la langue et la cloche vivaient sur la barre du client seulement :
                 cet espace n'avait aucun moyen de passer en sombre ni de voir ses notifications. --}}
            <x-theme-toggle />
            <div class="hidden lg:block">
                <x-language-switcher />
            </div>
            <x-cloche-notifications />

            <a href="{{ route('profile.show') }}"
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 transition">
                <img alt="" src="{{ Auth::user()->profile_photo_url }}"
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

    {{-- Trois `dispatch('toast', …)` de cet espace n'atteignaient RIEN : cette mise en page
         n'a jamais monte le composant. Une facture introuvable, un litige ouvert et un
         changement d'acces se faisaient en silence. --}}
    <x-toast />
    <x-ui.confirmation />

    {{-- La barre porte `backdrop-blur`, qui fait d'elle le bloc conteneur de tout `fixed`
         descendant : la bulle « bottom-6 right-6 » atterrissait a -25px, coupee en haut. --}}
    @auth
        <livewire:chatbot.assistant-widget />
    @endauth

    @livewireScripts

    {{-- Sans ce stack, aucun `@push('scripts')` de cet espace n'atteignait la page :
         `dessinerRepartition` restait indefinie et l'anneau ne pouvait pas se dessiner. --}}
    @stack('scripts')
</body>

</html>