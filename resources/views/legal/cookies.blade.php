@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-12 px-6">
    <h1 class="text-3xl font-black text-slate-900 mb-2">Politique de cookies</h1>
    <p class="text-sm text-slate-500 mb-8">Version 1.0 — 20/05/2026</p>

    <div class="prose prose-slate max-w-none space-y-6">
        <section>
            <h2 class="text-xl font-bold">Qu'est-ce qu'un cookie ?</h2>
            <p>Un cookie est un petit fichier déposé sur votre appareil par un site web. Il sert à mémoriser des informations (langue, session, préférences).</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Cookies que nous utilisons</h2>
            <table class="w-full text-sm border">
                <thead class="bg-slate-100">
                    <tr><th class="p-2 text-left">Type</th><th class="p-2 text-left">Nom</th><th class="p-2 text-left">Durée</th><th class="p-2 text-left">Finalité</th><th class="p-2 text-left">Consentement</th></tr>
                </thead>
                <tbody>
                    <tr class="border-t"><td class="p-2">Essentiel</td><td class="p-2">cleanux_session</td><td class="p-2">2h</td><td class="p-2">Authentification</td><td class="p-2">Non requis</td></tr>
                    <tr class="border-t"><td class="p-2">Essentiel</td><td class="p-2">XSRF-TOKEN</td><td class="p-2">2h</td><td class="p-2">Protection CSRF</td><td class="p-2">Non requis</td></tr>
                    <tr class="border-t"><td class="p-2">Préférence</td><td class="p-2">cleanux_locale</td><td class="p-2">1 an</td><td class="p-2">Langue choisie</td><td class="p-2">Non requis</td></tr>
                    <tr class="border-t"><td class="p-2">Analytics</td><td class="p-2">cleanux_anonymous</td><td class="p-2">13 mois</td><td class="p-2">Statistiques anonymes</td><td class="p-2">Requis</td></tr>
                    <tr class="border-t"><td class="p-2">Marketing</td><td class="p-2">_gid, _ga, etc.</td><td class="p-2">selon outil</td><td class="p-2">Mesure de performance, retargeting</td><td class="p-2">Requis</td></tr>
                </tbody>
            </table>
        </section>

        <section>
            <h2 class="text-xl font-bold">Gérer vos préférences</h2>
            <p>Vous pouvez à tout moment modifier vos choix en cliquant sur le bouton ci-dessous, ou via les paramètres de votre navigateur.</p>
            <button x-data="" x-on:click="window.dispatchEvent(new CustomEvent('open-cookie-banner'))"
                    class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500 mt-3">
                Modifier mes préférences cookies
            </button>
        </section>

        <section>
            <h2 class="text-xl font-bold">Cookies tiers</h2>
            <ul class="list-disc list-inside">
                <li>Stripe : checkout sécurisé (cookies essentiels)</li>
                <li>Google Maps : géolocalisation (cookies essentiels si carte affichée)</li>
                <li>Google Analytics / Mixpanel (si activé via consentement)</li>
            </ul>
        </section>
    </div>
</div>
@endsection
