<x-guest-layout
    :seoTitle="$seoTitle"
    :seoDescription="$seoDescription"
>

    {{-- Schema.org Service JSON-LD --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "Service",
        "name": "{{ $trade->name }}",
        "description": "{{ $seoDescription }}",
        "provider": {
            "@type": "Organization",
            "name": "Brio",
            "url": "{{ config('app.url') }}"
        },
        "areaServed": {
            "@type": "Country",
            "name": "Belgium"
        },
        "serviceType": "{{ $trade->name }}",
        "url": "{{ url()->current() }}",
        @if($trade->default_hourly_rate)
        "offers": {
            "@type": "Offer",
            "priceCurrency": "EUR",
            "price": "{{ $trade->default_hourly_rate }}",
            "availability": "https://schema.org/InStock",
            "priceSpecification": {
                "@type": "UnitPriceSpecification",
                "unitText": "hour"
            }
        }
        @else
        "offers": {
            "@type": "Offer",
            "availability": "https://schema.org/InStock",
            "priceCurrency": "EUR"
        }
        @endif
    }
    </script>

    {{-- HERO --}}
    <section class="cx-gradient-animated relative isolate overflow-hidden">
        <div class="mx-auto max-w-7xl px-6 pb-20 pt-16 sm:pb-28 sm:pt-20">
            <div class="mx-auto max-w-3xl text-center">

                {{-- Breadcrumb --}}
                <nav class="mb-6 flex items-center justify-center gap-2 text-xs" style="color:var(--cx-muted)" aria-label="Fil d'Ariane">
                    <a href="{{ route('home') }}" style="color:var(--cx-muted)" class="hover:underline">Accueil</a>
                    <span>/</span>
                    <a href="{{ route('services.index') }}" style="color:var(--cx-muted)" class="hover:underline">Services</a>
                    <span>/</span>
                    <span style="color:var(--cx-amber)">{{ $trade->name }}</span>
                    @if($cityLabel)
                        <span>/</span>
                        <span style="color:var(--cx-text)">{{ $cityLabel }}</span>
                    @endif
                </nav>

                <p class="text-xs font-bold uppercase tracking-[0.22em]" style="color:var(--cx-amber)">
                    Service professionnel
                </p>

                <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl" style="font-family:var(--cx-display);color:var(--cx-text)">
                    {{ $trade->name }}
                    @if($cityLabel)
                        <span class="block text-3xl font-bold sm:text-4xl" style="color:var(--cx-amber)">a {{ $cityLabel }}</span>
                    @else
                        <span class="block text-3xl font-bold sm:text-4xl" style="color:var(--cx-amber)">en Belgique</span>
                    @endif
                </h1>

                <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed" style="color:var(--cx-muted)">
                    {{ $seoDescription }}
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                    <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary inline-flex px-8 py-3 text-base font-semibold">
                        Réserver maintenant
                    </a>
                    @if(Route::has('providers.browse.public'))
                    <a href="{{ route('providers.browse.public') }}" class="cx-btn cx-btn--ghost inline-flex px-6 py-3 text-sm">
                        Voir les providers
                    </a>
                    @endif
                </div>

                @if($trade->default_hourly_rate)
                <p class="mt-5 text-sm" style="color:var(--cx-muted)">
                    A partir de <strong style="color:var(--cx-amber)">{{ number_format((float) $trade->default_hourly_rate, 0) }}€/heure</strong>
                    · Paiement apres prestation
                </p>
                @endif
            </div>
        </div>
    </section>

    {{-- TRUST SIGNALS --}}
    <section class="border-y" style="border-color:var(--cx-line);background:var(--cx-surface)">
        <div class="mx-auto max-w-7xl px-6 py-10">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl" style="background:rgba(255,182,72,0.1)">
                        <svg class="h-5 w-5" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold" style="color:var(--cx-text)">Providers verifies</p>
                        <p class="text-sm" style="color:var(--cx-muted)">KYC complet, assurance pro controlee</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl" style="background:rgba(255,182,72,0.1)">
                        <svg class="h-5 w-5" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold" style="color:var(--cx-text)">Paiement securise</p>
                        <p class="text-sm" style="color:var(--cx-muted)">Stripe 3DS, debit apres execution</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl" style="background:rgba(255,182,72,0.1)">
                        <svg class="h-5 w-5" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold" style="color:var(--cx-text)">Suivi GPS temps reel</p>
                        <p class="text-sm" style="color:var(--cx-muted)">Tracking en direct sur l'app mobile</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl" style="background:rgba(255,182,72,0.1)">
                        <svg class="h-5 w-5" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold" style="color:var(--cx-text)">Avis verifies</p>
                        <p class="text-sm" style="color:var(--cx-muted)">Notes publiques apres chaque mission</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-6 py-16">
        <div class="grid gap-12 lg:grid-cols-3">

            {{-- MAIN CONTENT --}}
            <div class="lg:col-span-2">

                {{-- Description --}}
                @if($trade->description)
                <div class="prose prose-invert max-w-none">
                    <h2 class="text-2xl font-bold" style="color:var(--cx-text)">
                        {{ $trade->name }} professionnel{{ $cityLabel ? ' a ' . $cityLabel : ' en Belgique' }}
                    </h2>
                    <p class="mt-4 text-base leading-relaxed" style="color:var(--cx-muted)">
                        {{ $trade->description }}
                    </p>
                </div>
                @endif

                {{-- City list (if no city selected) --}}
                @if(!$city && $zones->isNotEmpty())
                <div class="mt-12">
                    <h2 class="text-xl font-bold" style="color:var(--cx-text)">
                        {{ $trade->name }} par ville
                    </h2>
                    <p class="mt-2 text-sm" style="color:var(--cx-muted)">
                        Selectionnez votre ville pour voir les prestataires disponibles.
                    </p>
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach($zones->take(18) as $zone)
                        <a href="{{ route('services.show.city', [$trade->slug ?: $trade->code, Str::slug($zone->name)]) }}"
                           class="flex items-center justify-between rounded-xl border px-4 py-3 text-sm transition-colors hover:border-amber-400/50"
                           style="border-color:var(--cx-line);color:var(--cx-text)">
                            {{ $zone->name }}
                            <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- FAQ --}}
                <div class="mt-14">
                    <h2 class="text-xl font-bold" style="color:var(--cx-text)">
                        Questions frequentes — {{ $trade->name }}
                    </h2>
                    <div class="mt-6 divide-y" style="border-color:var(--cx-line)">

                        <details class="group py-5">
                            <summary class="flex cursor-pointer items-center justify-between font-medium" style="color:var(--cx-text)">
                                Comment reserver un {{ strtolower($trade->name) }} sur Brio ?
                                <svg class="h-5 w-5 flex-shrink-0 transition-transform group-open:rotate-180" style="color:var(--cx-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </summary>
                            <p class="mt-3 text-sm leading-relaxed" style="color:var(--cx-muted)">
                                Remplissez le formulaire de reservation en 2 minutes, decrivez votre besoin,
                                choisissez votre creneau horaire et votre zone geographique.
                                Un prestataire verifie vous est assigne automatiquement.
                            </p>
                        </details>

                        <details class="group py-5">
                            <summary class="flex cursor-pointer items-center justify-between font-medium" style="color:var(--cx-text)">
                                Quel est le tarif pour un {{ strtolower($trade->name) }} ?
                                <svg class="h-5 w-5 flex-shrink-0 transition-transform group-open:rotate-180" style="color:var(--cx-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </summary>
                            <p class="mt-3 text-sm leading-relaxed" style="color:var(--cx-muted)">
                                @if($trade->default_hourly_rate)
                                    Les tarifs commencent a partir de {{ number_format((float) $trade->default_hourly_rate, 0) }}€/heure.
                                    Le montant exact depend de la complexite de la mission, de la duree et de la localisation.
                                @else
                                    Les tarifs sont determines sur devis selon la complexite de votre mission.
                                @endif
                                Vous recevez un devis gratuit avant toute facturation.
                            </p>
                        </details>

                        <details class="group py-5">
                            <summary class="flex cursor-pointer items-center justify-between font-medium" style="color:var(--cx-text)">
                                Les prestataires sont-ils assures ?
                                <svg class="h-5 w-5 flex-shrink-0 transition-transform group-open:rotate-180" style="color:var(--cx-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </summary>
                            <p class="mt-3 text-sm leading-relaxed" style="color:var(--cx-muted)">
                                Oui, tous les prestataires {{ strtolower($trade->name) }} sur Brio sont verifies KYC
                                et disposent d'une assurance professionnelle valide.
                                En cas de probleme, notre SAV intervient sous 24h.
                            </p>
                        </details>

                        <details class="group py-5">
                            <summary class="flex cursor-pointer items-center justify-between font-medium" style="color:var(--cx-text)">
                                Quand est-ce que je paie ?
                                <svg class="h-5 w-5 flex-shrink-0 transition-transform group-open:rotate-180" style="color:var(--cx-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </summary>
                            <p class="mt-3 text-sm leading-relaxed" style="color:var(--cx-muted)">
                                Le paiement est debite uniquement apres la confirmation de fin de mission via QR code.
                                Votre carte est pre-autorisee au moment de la reservation mais rien n'est preleve avant.
                            </p>
                        </details>

                        @if($cityLabel)
                        <details class="group py-5">
                            <summary class="flex cursor-pointer items-center justify-between font-medium" style="color:var(--cx-text)">
                                Combien de prestataires {{ strtolower($trade->name) }} sont disponibles a {{ $cityLabel }} ?
                                <svg class="h-5 w-5 flex-shrink-0 transition-transform group-open:rotate-180" style="color:var(--cx-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </summary>
                            <p class="mt-3 text-sm leading-relaxed" style="color:var(--cx-muted)">
                                Le nombre de prestataires disponibles a {{ $cityLabel }} varie selon les creneaux.
                                Notre algorithme de matching vous connecte au prestataire le mieux note
                                et le plus proche de votre adresse.
                            </p>
                        </details>
                        @endif

                    </div>

                    {{-- FAQ JSON-LD --}}
                    <script type="application/ld+json">
                    {
                        "@@context": "https://schema.org",
                        "@type": "FAQPage",
                        "mainEntity": [
                            {
                                "@type": "Question",
                                "name": "Comment reserver un {{ strtolower($trade->name) }} sur Brio ?",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": "Remplissez le formulaire de reservation en 2 minutes. Un prestataire verifie vous est assigne automatiquement."
                                }
                            },
                            {
                                "@type": "Question",
                                "name": "Quel est le tarif pour un {{ strtolower($trade->name) }} ?",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": "{{ $trade->default_hourly_rate ? 'A partir de ' . number_format((float) $trade->default_hourly_rate, 0) . '€/heure.' : 'Tarif sur devis gratuit.' }} Paiement apres prestation."
                                }
                            },
                            {
                                "@type": "Question",
                                "name": "Les prestataires sont-ils assures ?",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": "Oui, tous les prestataires sont verifies KYC et disposent d'une assurance professionnelle valide."
                                }
                            }
                        ]
                    }
                    </script>
                </div>
            </div>

            {{-- SIDEBAR --}}
            <aside class="lg:col-span-1">
                <div class="sticky top-24">

                    {{-- Booking card --}}
                    <div class="rounded-2xl border p-6" style="background:var(--cx-surface);border-color:var(--cx-line)">
                        <p class="text-xs font-bold uppercase tracking-[0.2em]" style="color:var(--cx-amber)">
                            Devis gratuit
                        </p>
                        <h3 class="mt-2 text-lg font-bold" style="color:var(--cx-text)">
                            Réservez votre {{ strtolower($trade->name) }}
                        </h3>
                        @if($trade->default_hourly_rate)
                        <p class="mt-1 text-2xl font-extrabold" style="color:var(--cx-amber)">
                            {{ number_format((float) $trade->default_hourly_rate, 0) }}€<span class="text-base font-normal" style="color:var(--cx-muted)">/heure</span>
                        </p>
                        @endif
                        <ul class="mt-5 space-y-2 text-sm" style="color:var(--cx-muted)">
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Reservation en 2 minutes
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Annulation gratuite
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Paiement apres execution
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                SAV inclus
                            </li>
                        </ul>
                        <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary mt-6 flex w-full items-center justify-center py-3 text-sm font-semibold">
                            Réserver maintenant
                        </a>
                    </div>

                    {{-- Other trades link --}}
                    <div class="mt-6 rounded-xl border p-5 text-center" style="border-color:var(--cx-line)">
                        <p class="text-sm" style="color:var(--cx-muted)">
                            Besoin d'un autre service ?
                        </p>
                        <a href="{{ route('services.index') }}" class="mt-2 inline-flex text-sm font-semibold" style="color:var(--cx-amber)">
                            Voir tous nos services &rarr;
                        </a>
                    </div>
                </div>
            </aside>

        </div>
    </div>

</x-guest-layout>
