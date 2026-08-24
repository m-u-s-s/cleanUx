<x-guest-layout :cta="true" :title="$seoTitle" :description="$seoDescription">

    {{-- Schema.org SoftwareApplication / Product markup for each tier --}}
    @push('head')
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "ItemList",
        "name": "Tarifs Brio",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "item": {
                    "@type": "Product",
                    "name": "Brio Gratuit",
                    "description": "Jusqu'à 5 réservations par mois, accès à la marketplace.",
                    "offers": {
                        "@type": "Offer",
                        "price": "0",
                        "priceCurrency": "EUR",
                        "availability": "https://schema.org/InStock"
                    }
                }
            },
            {
                "@type": "ListItem",
                "position": 2,
                "item": {
                    "@type": "Product",
                    "name": "Brio Pro",
                    "description": "Réservations illimitées, choix prestataire, dispatch prioritaire.",
                    "offers": {
                        "@type": "Offer",
                        "price": "9.99",
                        "priceCurrency": "EUR",
                        "availability": "https://schema.org/InStock"
                    }
                }
            },
            {
                "@type": "ListItem",
                "position": 3,
                "item": {
                    "@type": "Product",
                    "name": "Brio Business",
                    "description": "Multi-sites, assurance incluse, account manager dédié.",
                    "offers": {
                        "@type": "Offer",
                        "price": "29.99",
                        "priceCurrency": "EUR",
                        "availability": "https://schema.org/InStock"
                    }
                }
            }
        ]
    }
    </script>
    @endpush

    {{-- HERO --}}
    <section class="relative isolate overflow-hidden cx-gradient-animated">
        <div class="mx-auto max-w-7xl px-6 pt-20 pb-16 sm:pt-28 sm:pb-20 text-center">
            <div class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                <x-ui.icon name="sparkles" class="w-3.5 h-3.5" />
                Tarifs simples, sans engagement
            </div>
            <h1 class="mt-6 text-4xl font-bold text-slate-900 sm:text-5xl cx-headline cx-balance">
                Le bon plan pour chaque besoin.
            </h1>
            <p class="mt-4 text-lg text-slate-600 cx-body-readable mx-auto max-w-2xl">
                Commencez gratuitement, passez Pro quand vous en avez besoin. Aucune carte requise pour démarrer.
            </p>
        </div>
    </section>

    {{-- PRICING CARDS --}}
    <section class="py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-3">

                {{-- FREE --}}
                <div class="brio-glass rounded-2xl p-8 flex flex-col" data-cx-reveal>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Gratuit</p>
                        <div class="mt-3 flex items-end gap-1">
                            <span class="text-4xl font-bold text-slate-900 cx-tabular" style="font-family: 'Space Grotesk', sans-serif;">0€</span>
                            <span class="mb-1 text-sm text-slate-500">/mois</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                            Pour explorer la marketplace et réserver ponctuellement.
                        </p>
                    </div>

                    <ul class="mt-8 space-y-3 text-sm text-slate-700 flex-1">
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                            Jusqu'à 5 réservations / mois
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                            Paiement sécurisé Stripe
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                            Tracking GPS en direct
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                            Support par email
                        </li>
                        <li class="flex items-start gap-3 text-slate-400">
                            <x-ui.icon name="x-mark" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                            Choix du prestataire
                        </li>
                        <li class="flex items-start gap-3 text-slate-400">
                            <x-ui.icon name="x-mark" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                            Dispatch prioritaire
                        </li>
                        <li class="flex items-start gap-3 text-slate-400">
                            <x-ui.icon name="x-mark" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                            Historique étendu
                        </li>
                    </ul>

                    <div class="mt-8">
                        <a href="{{ route('register') }}"
                           class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-center text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition cursor-pointer">
                            Commencer
                        </a>
                    </div>
                </div>

                {{-- PRO (highlighted) --}}
                <div class="relative brio-glass rounded-2xl p-8 flex flex-col ring-2 ring-accent-amber" data-cx-reveal data-cx-delay="100">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-accent-amber px-3 py-1 text-xs font-bold text-slate-900 shadow-md">
                            <x-ui.icon name="star" class="w-3 h-3" />
                            Populaire
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Pro</p>
                        <div class="mt-3 flex items-end gap-1">
                            <span class="text-4xl font-bold text-slate-900 cx-tabular" style="font-family: 'Space Grotesk', sans-serif;">9.99€</span>
                            <span class="mb-1 text-sm text-slate-500">/mois</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                            Pour les particuliers qui réservent régulièrement.
                        </p>
                    </div>

                    <ul class="mt-8 space-y-3 text-sm text-slate-700 flex-1">
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                            <strong>Réservations illimitées</strong>
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                            Paiement sécurisé Stripe
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                            Tracking GPS en direct
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                            <strong>Choix du prestataire</strong>
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                            <strong>Dispatch prioritaire</strong>
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                            Historique étendu
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                            Support prioritaire chat
                        </li>
                    </ul>

                    <div class="mt-8">
                        <a href="{{ route('register') }}"
                           class="block w-full rounded-xl bg-accent-amber px-4 py-3 text-center text-sm font-bold text-slate-900 shadow-md hover:opacity-90 transition brio-glow-amber cursor-pointer">
                            Essai gratuit 14 jours
                        </a>
                    </div>
                </div>

                {{-- BUSINESS --}}
                <div class="brio-glass rounded-2xl p-8 flex flex-col" data-cx-reveal data-cx-delay="200">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Business</p>
                        <div class="mt-3 flex items-end gap-1">
                            <span class="text-4xl font-bold text-slate-900 cx-tabular" style="font-family: 'Space Grotesk', sans-serif;">29.99€</span>
                            <span class="mb-1 text-sm text-slate-500">/mois</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                            Pour les sociétés multi-sites et facility managers.
                        </p>
                    </div>

                    <ul class="mt-8 space-y-3 text-sm text-slate-700 flex-1">
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5" />
                            Tout le plan Pro inclus
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5" />
                            <strong>Multi-sites & multi-membres</strong>
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5" />
                            <strong>Assurance RC pro incluse</strong>
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5" />
                            Account manager dédié
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5" />
                            Facturation Peppol / Factur-X
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5" />
                            API + Webhooks HMAC B2B
                        </li>
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5" />
                            SLA 99.9% garanti
                        </li>
                    </ul>

                    <div class="mt-8">
                        <a href="mailto:business@brio.be"
                           class="block w-full rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-center text-sm font-semibold text-brand-700 hover:bg-brand-100 transition cursor-pointer">
                            Contacter l'equipe
                        </a>
                    </div>
                </div>

            </div>

            {{-- Billing note --}}
            <p class="mt-8 text-center text-xs text-slate-400">
                Tarifs HT. Facturation mensuelle ou annuelle (2 mois offerts). Annulation a tout moment.
            </p>
        </div>
    </section>

    {{-- COMPARISON TABLE (desktop) --}}
    <section class="py-12 sm:py-16 bg-slate-50/40">
        <div class="mx-auto max-w-5xl px-6">
            <h2 class="text-2xl font-bold text-slate-900 text-center mb-10 cx-headline">
                Comparer les formules
            </h2>
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="px-6 py-4 text-left font-semibold text-slate-700 w-1/2">Fonctionnalite</th>
                            <th class="px-4 py-4 text-center font-semibold text-slate-500">Gratuit</th>
                            <th class="px-4 py-4 text-center font-bold text-amber-600">Pro</th>
                            <th class="px-4 py-4 text-center font-semibold text-brand-600">Business</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-6 py-3 text-slate-700">Reservations / mois</td>
                            <td class="px-4 py-3 text-center text-slate-500">5</td>
                            <td class="px-4 py-3 text-center font-medium text-amber-600">Illimitees</td>
                            <td class="px-4 py-3 text-center font-medium text-brand-600">Illimitees</td>
                        </tr>
                        <tr class="bg-slate-50/30">
                            <td class="px-6 py-3 text-slate-700">Tracking GPS</td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 text-slate-700">Choix du prestataire</td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                        </tr>
                        <tr class="bg-slate-50/30">
                            <td class="px-6 py-3 text-slate-700">Dispatch prioritaire</td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 text-slate-700">Historique etendu</td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                        </tr>
                        <tr class="bg-slate-50/30">
                            <td class="px-6 py-3 text-slate-700">Assurance RC pro incluse</td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 text-slate-700">Multi-sites</td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                        </tr>
                        <tr class="bg-slate-50/30">
                            <td class="px-6 py-3 text-slate-700">API + Webhooks B2B</td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3 text-slate-700">Account manager dedie</td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="x-mark" class="w-4 h-4 text-slate-300 mx-auto" /></td>
                            <td class="px-4 py-3 text-center"><x-ui.icon name="check" class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-20 bg-white" data-cx-reveal>
        <div class="max-w-3xl mx-auto px-6">
            <h2 class="text-2xl font-bold text-center text-slate-900 mb-10 cx-headline">
                Questions sur les tarifs
            </h2>
            <div class="space-y-4">
                @foreach([
                    [
                        'q' => 'Puis-je changer de plan a tout moment ?',
                        'a' => 'Oui. Vous pouvez passer au plan superieur ou inferieur a n\'importe quel moment. Le changement prend effet immediatement et la facturation est calculee au prorata.',
                    ],
                    [
                        'q' => 'Y a-t-il un engagement ?',
                        'a' => 'Non, aucun. Tous les plans sont sans engagement. Vous pouvez annuler avant le prochain cycle de facturation sans frais. Votre abonnement reste actif jusqu\'a la fin de la periode deja payee.',
                    ],
                    [
                        'q' => 'La periode d\'essai Pro necessite-t-elle une carte ?',
                        'a' => 'Non. Vous pouvez demarrer l\'essai gratuit de 14 jours sans saisir de carte bancaire. Vous n\'etes debite qu\'apres la fin de la periode d\'essai si vous choisissez de continuer.',
                    ],
                    [
                        'q' => 'Que se passe-t-il si je depasse les 5 reservations du plan Gratuit ?',
                        'a' => 'Vous serez invite a passer au plan Pro pour continuer a reserver. Vos reservations en cours ne sont pas affectees.',
                    ],
                    [
                        'q' => 'Le plan Business inclut-il plusieurs utilisateurs ?',
                        'a' => 'Oui. Business permet la gestion multi-sites avec des roles personnalises pour votre equipe (gestionnaire, valideur, visualisation seule). Le nombre d\'utilisateurs est adapte a votre volume.',
                    ],
                    [
                        'q' => 'Comment fonctionne la facturation annuelle ?',
                        'a' => 'En choisissant l\'abonnement annuel, vous beneficiez de l\'equivalent de 2 mois offerts par rapport a la facturation mensuelle. La facture est emise en debut d\'annee.',
                    ],
                ] as $faq)
                    <details class="brio-glass rounded-xl p-5 group cursor-pointer" data-cx-reveal>
                        <summary class="font-semibold text-slate-900 list-none flex justify-between items-center gap-4">
                            <span>{{ $faq['q'] }}</span>
                            <span class="text-brand-500 flex-shrink-0 group-open:rotate-45 transition-transform">+</span>
                        </summary>
                        <p class="text-sm text-slate-600 mt-3 leading-relaxed">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA FINAL --}}
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand-600 to-purple-700 py-24">
        <div class="mx-auto max-w-4xl px-6 text-center">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                Pret a commencer ?
            </h2>
            <p class="mt-4 text-base leading-7 text-brand-100">
                Inscription en 30 secondes. Aucune carte requise pour explorer.
            </p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                <a href="{{ route('register') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-base font-semibold text-brand-700 shadow-soft-md hover:bg-brand-50 transition cursor-pointer">
                    Creer un compte gratuit
                    <x-ui.icon name="arrow-right" class="w-5 h-5" />
                </a>
                <a href="{{ route('premium.offer') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-6 py-3 text-base font-semibold text-white backdrop-blur hover:bg-white/20 transition cursor-pointer">
                    Voir l'offre Pro
                </a>
            </div>
        </div>
    </section>

</x-guest-layout>
