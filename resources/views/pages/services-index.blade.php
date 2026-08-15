<x-guest-layout
    :cta="true"
    :seoTitle="'Tous nos services a domicile — Brio Belgique'"
    :seoDescription="'Decouvrez 30+ metiers de services a domicile : nettoyage, peinture, plomberie, jardinage, babysitting et plus. Providers verifies, paiement securise, suivi en temps reel.'"
>

    {{-- HERO --}}
    <section class="cx-gradient-animated relative isolate overflow-hidden">
        <div class="mx-auto max-w-7xl px-6 pb-20 pt-16 sm:pb-28 sm:pt-20">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-[0.22em]" style="color:var(--cx-amber)">
                    Marketplace multi-metiers
                </p>
                <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl" style="font-family:var(--cx-display);color:var(--cx-text)">
                    Tous nos services<br>a domicile
                </h1>
                <p class="mt-5 text-lg leading-relaxed" style="color:var(--cx-muted)">
                    30+ corps de metiers, des providers verifies KYC, un paiement securise Stripe
                    et un suivi en temps reel de chaque prestation.
                </p>
            </div>
        </div>
    </section>

    {{-- TRADE GRID --}}
    <section class="mx-auto max-w-7xl px-6 py-16 sm:py-20">

        {{-- Trust bar --}}
        <div class="mb-12 flex flex-wrap items-center justify-center gap-6 text-sm" style="color:var(--cx-muted)">
            <span class="flex items-center gap-2">
                <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Providers verifies KYC
            </span>
            <span class="flex items-center gap-2">
                <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Paiement securise Stripe
            </span>
            <span class="flex items-center gap-2">
                <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Suivi GPS en temps reel
            </span>
            <span class="flex items-center gap-2">
                <svg class="h-4 w-4 flex-shrink-0" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                Note publique apres prestation
            </span>
        </div>

        @if($trades->isEmpty())
            <div class="py-24 text-center" style="color:var(--cx-muted)">
                <p class="text-lg">Aucun service disponible pour le moment.</p>
                <a href="{{ route('home') }}" class="cx-btn cx-btn--primary mt-6 inline-flex">Retour a l'accueil</a>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($trades as $trade)
                <a href="{{ route('services.show', $trade->slug ?: $trade->code) }}"
                   class="group relative flex flex-col overflow-hidden rounded-2xl border transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                   style="background:var(--cx-surface);border-color:var(--cx-line)">

                    {{-- Color accent strip --}}
                    <div class="h-1 w-full" style="background:{{ $trade->color ?? 'var(--cx-amber)' }}"></div>

                    <div class="flex flex-1 flex-col p-6">
                        {{-- Icon --}}
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl text-2xl"
                             style="background:rgba(255,182,72,0.1)">
                            @if($trade->icon)
                                <span>{{ $trade->icon }}</span>
                            @else
                                <svg class="h-6 w-6" style="color:var(--cx-amber)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            @endif
                        </div>

                        <h2 class="text-lg font-bold" style="color:var(--cx-text)">{{ $trade->name }}</h2>

                        @if($trade->short_description)
                            <p class="mt-2 flex-1 text-sm leading-relaxed" style="color:var(--cx-muted)">
                                {{ $trade->short_description }}
                            </p>
                        @else
                            <p class="mt-2 flex-1 text-sm leading-relaxed" style="color:var(--cx-muted)">
                                Reservez un {{ strtolower($trade->name) }} verifie en Belgique.
                                Disponible rapidement, devis gratuit.
                            </p>
                        @endif

                        <div class="mt-5 flex items-center justify-between">
                            @if($trade->default_hourly_rate)
                                <span class="text-sm font-semibold" style="color:var(--cx-amber)">
                                    A partir de {{ number_format((float) $trade->default_hourly_rate, 0) }}€/h
                                </span>
                            @else
                                <span class="text-sm" style="color:var(--cx-muted)">Devis sur demande</span>
                            @endif

                            <span class="flex items-center gap-1 text-xs" style="color:var(--cx-muted)">
                                Voir
                                <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        @endif

        {{-- CTA --}}
        <div class="mt-16 rounded-2xl border p-10 text-center" style="background:var(--cx-surface);border-color:var(--cx-line)">
            <h2 class="text-2xl font-bold" style="color:var(--cx-text)">Prêt à réserver ?</h2>
            <p class="mt-3 text-base" style="color:var(--cx-muted)">
                Devis gratuit en 2 minutes. Paiement apres la prestation.
            </p>
            <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary mt-6 inline-flex px-8 py-3 text-base font-semibold">
                Réserver une mission
            </a>
        </div>
    </section>

    {{-- JSON-LD ItemList --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "ItemList",
        "name": "Services Brio",
        "description": "Tous les services a domicile disponibles sur Brio en Belgique",
        "url": "{{ route('services.index') }}",
        "itemListElement": [
            @foreach($trades as $idx => $trade)
            {
                "@type": "ListItem",
                "position": {{ $idx + 1 }},
                "name": "{{ $trade->name }}",
                "url": "{{ route('services.show', $trade->slug ?: $trade->code) }}"
            }{{ !$loop->last ? ',' : '' }}
            @endforeach
        ]
    }
    </script>

</x-guest-layout>
