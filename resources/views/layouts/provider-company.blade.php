<!DOCTYPE html>
{{--
    Espace prestataire. Suit l'idiome de la console d'administration : thème clair par défaut,
    `data-chrome="primary-nav"` pour que le mode embarqué masque la barre.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <x-theme-amorce />
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
    <x-barre-societe espace="provider-company" />
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

    {{-- Trois `dispatch('toast', …)` de cet espace n'atteignaient RIEN : cette mise en page
         n'a jamais monte le composant. Une facture introuvable, un litige ouvert et un
         changement d'acces se faisaient en silence. --}}
    <x-toast />
    <x-ui.confirmation />

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

    {{-- La barre porte `backdrop-blur`, qui fait d'elle le bloc conteneur de tout `fixed`
         descendant : la bulle « bottom-6 right-6 » atterrissait a -25px, coupee en haut. --}}
    @auth
        <livewire:chatbot.assistant-widget />
    @endauth
    @endunless

    {{-- Sans ce stack, les `@push('scripts')` des composants ci-dessus n'atteignaient pas la page. --}}
    @stack('scripts')
</body>

</html>
