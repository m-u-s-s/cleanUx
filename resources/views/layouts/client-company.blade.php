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

    {{-- ── Topbar ── La MEME que celle des autres tableaux de bord : elle deduit l'espace de
         la route courante et tire ses liens du meme registre. --}}
    @unless($embedded ?? false)
    <div data-chrome="primary-nav">
        @livewire('navigation-menu')
    </div>
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