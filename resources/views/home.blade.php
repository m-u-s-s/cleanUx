<x-guest-layout>
    {{-- ============================================================
         CleanUx — Landing page (refonte Stripe/Linear-light, 21/05/2026)
         Direction : sérieux SaaS multi-métiers B2B + B2C.
         Clean, dense info, trust signals.
         ============================================================ --}}

    {{-- HERO --}}
    <section class="relative isolate overflow-hidden bg-white">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-50/40 via-white to-white"></div>
        <div class="mx-auto max-w-7xl px-6 pt-20 pb-24 sm:pt-28 sm:pb-32">
            <div class="mx-auto max-w-3xl text-center">
                <div class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                    <x-ui.icon name="sparkles" class="w-3.5 h-3.5" />
                    Marketplace multi-métiers • B2C & B2B
                </div>
                <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-6xl">
                    L'OS des services pros,
                    <span class="bg-gradient-to-br from-brand-600 to-purple-600 bg-clip-text text-transparent">
                        à la demande.
                    </span>
                </h1>
                <p class="mt-6 text-lg leading-8 text-slate-600">
                    Nettoyage, peinture, plomberie, jardinage, babysitting — un seul compte, trouvez un prestataire vérifié près de chez vous en quelques clics. Devis IA depuis photo, paiement sécurisé Stripe, satisfaction garantie.
                </p>
                <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                    <x-ui.button href="{{ route('booking.create') }}" variant="primary" size="lg" icon="arrow-right" iconPosition="right">
                        Réserver une mission
                    </x-ui.button>
                    @if (Route::has('providers.browse.public'))
                        <x-ui.button href="{{ route('providers.browse.public') }}" variant="secondary" size="lg" icon="magnifying-glass">
                            Trouver un prestataire
                        </x-ui.button>
                    @endif
                </div>
                <p class="mt-6 text-sm text-slate-500">
                    Aucune carte requise pour explorer •
                    <span class="inline-flex items-center gap-1 ml-1">
                        <x-ui.icon name="shield-check" class="w-4 h-4 text-emerald-500" />
                        KYC validé · Stripe Connect · Assurance incluse
                    </span>
                </p>
            </div>
        </div>
    </section>

    {{-- TRUST BAR --}}
    <section class="border-y border-slate-100 bg-slate-50/50 py-8">
        <div class="mx-auto max-w-7xl px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-wider text-slate-500">
                30+ métiers · 6 langues · Conformité RGPD + Factur-X EU
            </p>
            <div class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-4 lg:grid-cols-6">
                @foreach (['Nettoyage', 'Peinture', 'Plomberie', 'Jardinage', 'Babysitting', 'Toiture'] as $trade)
                    <div class="flex items-center justify-center text-sm font-medium text-slate-600">
                        {{ $trade }}
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FEATURES SHOWCASE --}}
    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Comment ça marche</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    Trouvez. Réservez. C'est fait.
                </h2>
                <p class="mt-4 text-base leading-7 text-slate-600">
                    Plus de devis interminables, plus de no-shows. Notre IA estime, notre matching trouve, notre prestataire intervient — tracking GPS en temps réel.
                </p>
            </div>

            <div class="mx-auto mt-16 grid max-w-5xl grid-cols-1 gap-8 md:grid-cols-3">
                <x-ui.card padding="lg" class="text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                        <x-ui.icon name="camera" class="w-6 h-6" />
                    </div>
                    <h3 class="mt-5 text-base font-semibold text-slate-900">Devis IA depuis photo</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Prenez une photo, choisissez le métier — recevez une fourchette de prix en 10 secondes, sans appel commercial.
                    </p>
                </x-ui.card>

                <x-ui.card padding="lg" class="text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200">
                        <x-ui.icon name="badge-check" class="w-6 h-6" />
                    </div>
                    <h3 class="mt-5 text-base font-semibold text-slate-900">Prestataires vérifiés KYC</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Identité contrôlée Onfido/Veriff, casier judiciaire vérifié, assurance RC pro incluse sur chaque mission.
                    </p>
                </x-ui.card>

                <x-ui.card padding="lg" class="text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 text-purple-600 ring-1 ring-purple-200">
                        <x-ui.icon name="map-pin" class="w-6 h-6" />
                    </div>
                    <h3 class="mt-5 text-base font-semibold text-slate-900">Tracking GPS en direct</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Suivez l'arrivée de votre prestataire sur carte, ETA en temps réel, géofence d'arrivée auto.
                    </p>
                </x-ui.card>
            </div>
        </div>
    </section>

    {{-- DIFFERENTIATING SECTION : multi-trades bundle --}}
    <section class="bg-slate-50/40 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Différenciateur</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        Plusieurs métiers ?<br>
                        <span class="text-brand-600">Un seul chantier groupé.</span>
                    </h2>
                    <p class="mt-6 text-base leading-7 text-slate-600">
                        Rénovation salle de bain ? Carreleur + plombier + peintre + électricien — orchestrés dans le bon ordre, prix groupé, facture consolidée, 1 seul interlocuteur. <strong>Aucun concurrent ne fait ça.</strong>
                    </p>
                    <ul class="mt-8 space-y-3 text-sm text-slate-700">
                        <li class="flex gap-3">
                            <x-ui.icon name="check" class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                            Dépendances cascade (carreleur avant peintre)
                        </li>
                        <li class="flex gap-3">
                            <x-ui.icon name="check" class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                            Discount groupage automatique
                        </li>
                        <li class="flex gap-3">
                            <x-ui.icon name="check" class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                            Suivi unifié & facturation consolidée
                        </li>
                    </ul>
                    <div class="mt-8">
                        <x-ui.button href="{{ route('booking.create') }}" variant="outline" icon="arrow-right" iconPosition="right">
                            Démarrer un chantier groupé
                        </x-ui.button>
                    </div>
                </div>

                <div class="relative">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-soft-md">
                        <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3">
                            <p class="text-xs font-mono text-slate-500">bundle_demo_renovation</p>
                            <p class="text-sm font-semibold text-slate-900">Rénovation salle de bain</p>
                        </div>
                        <div class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-slate-400">1.</span>
                                    <x-ui.icon name="wrench" class="w-4 h-4 text-brand-600" />
                                    <span class="text-slate-700"><strong>Plombier</strong> — dépose ancienne robinetterie</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">320€</span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-slate-400">2.</span>
                                    <x-ui.icon name="cube" class="w-4 h-4 text-amber-600" />
                                    <span class="text-slate-700"><strong>Carreleur</strong> — pose carrelage sol + mur</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">1 240€</span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-slate-400">3.</span>
                                    <x-ui.icon name="sparkles" class="w-4 h-4 text-purple-600" />
                                    <span class="text-slate-700"><strong>Peintre</strong> — plafond + finitions</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">480€</span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-slate-400">4.</span>
                                    <x-ui.icon name="bolt" class="w-4 h-4 text-yellow-600" />
                                    <span class="text-slate-700"><strong>Électricien</strong> — éclairage LED + miroir</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">220€</span>
                            </div>
                        </div>
                        <div class="bg-emerald-50/50 px-5 py-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Total avec groupage</span>
                                <span class="text-lg font-bold text-emerald-700">2 084€ <span class="text-xs font-medium line-through text-slate-400 ml-2">2 260€</span></span>
                            </div>
                            <p class="mt-1 text-xs text-emerald-600">Économie groupage : 176€ (−8%)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- B2B SECTION --}}
    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Pour les entreprises</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    Facility management, simplifié.
                </h2>
                <p class="mt-4 text-base leading-7 text-slate-600">
                    API Tokens, webhooks B2B HMAC, multi-sites, bulk booking CSV, factures Peppol/Factur-X 09/2026-ready. Conçu pour scaler de 5 à 5000 sites.
                </p>
            </div>
            <div class="mx-auto mt-16 grid max-w-5xl grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <x-ui.icon name="building-office" class="w-6 h-6 text-brand-600" />
                    <h3 class="mt-4 font-semibold text-slate-900">Multi-sites & multi-membres</h3>
                    <p class="mt-2 text-sm text-slate-600">Plusieurs locaux, équipe avec rôles personnalisés, validation hiérarchique.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <x-ui.icon name="receipt" class="w-6 h-6 text-brand-600" />
                    <h3 class="mt-4 font-semibold text-slate-900">Facturation Peppol</h3>
                    <p class="mt-2 text-sm text-slate-600">Factur-X XML CII embedded, conformité réglementation 09/2026 FR. Export FEC DGFiP/Sage/QuickBooks.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <x-ui.icon name="key" class="w-6 h-6 text-brand-600" />
                    <h3 class="mt-4 font-semibold text-slate-900">API + Webhooks HMAC</h3>
                    <p class="mt-2 text-sm text-slate-600">18 scopes, rotation tokens, webhooks signés HMAC SHA256 retry exponentiel.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA FINAL --}}
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand-600 to-purple-700 py-24">
        <div class="mx-auto max-w-4xl px-6 text-center">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                Prêt à essayer ?
            </h2>
            <p class="mt-4 text-base leading-7 text-brand-100">
                Inscription en 30 secondes. Aucune carte requise pour explorer.
            </p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                <a href="{{ route('register') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-base font-semibold text-brand-700 shadow-soft-md hover:bg-brand-50 transition">
                    Créer un compte gratuit
                    <x-ui.icon name="arrow-right" class="w-5 h-5" />
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-6 py-3 text-base font-semibold text-white backdrop-blur hover:bg-white/20 transition">
                    Se connecter
                </a>
            </div>
        </div>
    </section>
</x-guest-layout>
