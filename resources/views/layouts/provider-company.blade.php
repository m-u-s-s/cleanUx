<!DOCTYPE html>
{{--
    L'ESPACE PRESTATAIRE SUIT L'IDIOME DE LA CONSOLE D'ADMINISTRATION.

    Il portait un thème SOMBRE à lui seul — `<html class="dark">` forcé, `bg-slate-900`, accent
    ambre — quand toutes les autres surfaces d'outil de ce produit sont claires. Une même marque
    n'a pas deux apparences selon le rôle de qui regarde : un gérant qui passe de son espace au
    tableau de bord admin avait l'impression de changer d'application.

    `class="dark"` EST RETIRÉ DU `<html>`, ET C'EST LE POINT CENTRAL. Il était écrit en dur, donc
    insensible à la préférence du compte : cet espace ignorait le sélecteur de thème que
    `layouts/app.blade.php` pilote pour tout le reste. Le thème suit désormais le même mécanisme
    qu'ailleurs — `localStorage` puis `theme_preference`, défaut système.

    `data-chrome="primary-nav"` et le `@unless($embedded ?? false)` sont CONSERVÉS : le mode
    embarqué (WebView mobile) masque la barre par ce marqueur, et trois tests le figent.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{
        theme: localStorage.getItem('theme') ?? '{{ auth()->user()?->theme_preference ?? 'system' }}',
    }"
    x-init="
        const appliquer = () => {
            const sombre = theme === 'dark'
                || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', sombre);
        };
        appliquer();
        $watch('theme', () => { localStorage.setItem('theme', theme); appliquer(); });
    ">

<head>
    {{-- Phase 8 — PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <x-brand.head space="provider" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ Auth::user()->currentOrganization?->name ?? 'Brio' }} — Espace prestataire</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

{{--
    Même fond que `layouts/app.blade.php` : le `bg-slate-50/30` est semi-transparent et laisse
    passer le dégradé défini dans `resources/css/base.css`. Y mettre un `bg-slate-50` opaque
    masquerait ce dégradé et donnerait un gris plat que l'admin n'a pas.
--}}
<body class="font-sans antialiased text-slate-800 bg-slate-50/30 selection:bg-brand-100 selection:text-brand-900">

    {{-- ── Topbar ── --}}
    @unless($embedded ?? false)
    <nav data-chrome="primary-nav" aria-label="Navigation principale"
        class="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-slate-100 bg-white/95 px-4 backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
        <div class="flex items-center gap-3">
            <a href="{{ route('provider-company.dashboard') }}"
                class="flex items-center gap-2 text-lg font-black text-slate-900 dark:text-white">
                <x-brand.logo space="provider" :size="32" />
                {{-- L'accent suit `brio-btn-primary` (sky), pas l'ambre qui n'appartenait qu'ici. --}}
                Brio <span class="text-sky-600 dark:text-sky-400">Pro</span>
            </a>
            <div class="hidden sm:flex items-center gap-1">
                {{-- Les liens vivaient en dur ici. Ils viennent désormais de `config/modules.php`,
                     comme ceux de la navbar et de la page Modules — « Sites desservis » compris,
                     ajouté au registre plutôt qu'à cette liste. --}}
                @foreach (\App\Support\Navigation\ModuleCatalogue::principaux('provider-company') as $link)
                <a href="{{ route($link['route']) }}"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition
                           {{ request()->routeIs($link['route'])
                               ? 'bg-slate-100 text-slate-900 font-medium dark:bg-slate-700 dark:text-white'
                               : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200' }}">
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
                                   ? 'bg-slate-100 text-slate-900 font-medium dark:bg-slate-700 dark:text-white'
                                   : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200' }}">
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
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-slate-100 dark:hover:bg-slate-800">
                <img src="{{ Auth::user()->profile_photo_url }}"
                    class="h-7 w-7 rounded-full border border-slate-200 object-cover dark:border-slate-600">
                <span class="hidden sm:block text-sm text-slate-600 dark:text-slate-300">{{ str(Auth::user()->name)->before(' ') }}</span>
            </a>
        </div>
    </nav>
    @endunless

    {{--
        Le contenu est enveloppé dans `brio-page`, comme celui de `layouts/app.blade.php` : même
        largeur maximale, même rythme vertical. Les écrans portaient chacun leur propre
        `min-h-screen … p-6`, d'où des marges qui ne tombaient pas au même endroit que sur l'admin.
    --}}
    <main class="px-3 py-5 sm:px-6 lg:px-8">
        <div class="brio-page animate-fade-in">
            {{ $slot }}
        </div>
    </main>

    @livewireScripts

    {{--
        LA BARRE DU BAS N'APPARTIENT NI AU CLIENT, NI À L'APPLICATION NATIVE.

        Deux défauts tenaient dans cette seule ligne, relevés en ouvrant « Planning et absences »
        depuis l'application prestataire.

        1. `<x-mobile-bottom-nav />` sans `items` retombe sur ses valeurs par défaut, qui sont
           CELLES DU CLIENT : « Mes RDV », « Réserver » (`client.rendezvous.*`). Une société
           prestataire lisait donc, en bas de son propre espace, une invitation à commander une
           prestation. `<x-ui.mobile-bottom-nav />` déduit le rôle de l'utilisateur et vérifie
           chaque route par `Route::has()` — il ne rend rien plutôt que d'inventer une destination.

        2. Elle était posée HORS du `@unless($embedded)` qui protège tout le reste de ce gabarit
           (lignes 54 à 106). Embarquée dans la WebView de l'application native, la page ajoutait
           donc sa propre barre de navigation web SOUS la barre d'onglets native — deux
           navigations empilées, occupant un tiers de l'écran.

        `layouts/app.blade.php` applique déjà exactement ce traitement à ses trois composants de
        chrome ; ce gabarit-ci était resté en arrière.
    --}}
    @unless($embedded ?? false)
    <x-ui.mobile-bottom-nav />
    <x-pwa-install-prompt />
    @endunless

    {{-- Sans ce stack, les `@push('scripts')` des composants ci-dessus n'atteignaient pas la page. --}}
    @stack('scripts')
</body>

</html>
