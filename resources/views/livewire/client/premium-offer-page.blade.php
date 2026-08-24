<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">

    {{-- Hero --}}
    <section class="rounded-2xl border border-slate-200/80 bg-white shadow-soft overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2">
            <div class="p-8 md:p-10">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                    <x-ui.icon name="star" class="w-3 h-3" />
                    Offre Premium mensuelle
                </span>

                <h1 class="mt-4 text-3xl md:text-4xl font-bold tracking-tight text-slate-900">
                    Un service plus personnalisé, plus simple à gérer
                </h1>

                <p class="mt-3 text-base text-slate-600 leading-relaxed">
                    Passez à Premium pour choisir vos employés favoris, consulter leurs disponibilités
                    et profiter d'une expérience plus fluide pour vos prestations régulières.
                </p>

                <div class="mt-7 flex flex-wrap gap-2">
                    @if($isPremium)
                        <span class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700">
                            <x-ui.icon name="check-circle" class="w-4 h-4" />
                            Vous êtes déjà Premium
                        </span>
                    @else
                        <form method="POST" action="{{ route('premium.checkout') }}" class="contents">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 active:bg-amber-700 transition">
                                <x-ui.icon name="star" class="w-4 h-4" />
                                <span>Passer en Premium</span>
                            </button>
                        </form>
                    @endif

                    <a href="#comparatif"
                        class="brio-btn-secondary inline-flex items-center justify-center gap-2">
                        <span>Comparer les offres</span>
                        <x-ui.icon name="arrow-down" class="w-4 h-4" />
                    </a>
                </div>

                {{-- Trust badges --}}
                <div class="mt-6 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="shield-check" class="w-3.5 h-3.5 text-emerald-500" />
                        Sans engagement
                    </span>
                    <span class="text-slate-300">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="badge-check" class="w-3.5 h-3.5 text-emerald-500" />
                        Annulable à tout moment
                    </span>
                    <span class="text-slate-300">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="lock-closed" class="w-3.5 h-3.5 text-emerald-500" />
                        Paiement Stripe
                    </span>
                </div>
            </div>

            {{-- Tarif clair --}}
            <div class="relative bg-gradient-to-br from-amber-500 to-amber-600 text-white p-8 md:p-10 flex flex-col justify-center overflow-hidden">
                <div class="absolute -top-12 -right-12 h-48 w-48 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-16 -left-16 h-56 w-56 rounded-full bg-white/5"></div>

                <p class="relative text-xs font-semibold uppercase tracking-wide text-amber-50">Tarif mensuel</p>
                <div class="relative mt-3 flex items-baseline gap-1">
                    <span class="text-5xl font-bold"><x-money :amount="(float) ($premiumPrice)" :decimals="0" /></span>
                    <span class="text-amber-100 text-lg font-semibold">/ mois</span>
                </div>

                <p class="relative mt-4 text-amber-50 text-sm">
                    Une formule claire pour les clients qui veulent plus de continuité et de confort.
                </p>

                <ul class="relative mt-6 space-y-2 text-sm">
                    @foreach ([
                        'Choisissez vos employés favoris',
                        'Consultez leurs disponibilités',
                        'Réservez plus facilement vos prestations régulières',
                        'Service plus personnalisé',
                    ] as $feature)
                        <li class="inline-flex items-start gap-2 w-full">
                            <x-ui.icon name="check-circle" class="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-100" />
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Pourquoi Premium - 4 cards --}}
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach([
            ['heart',    'Même logique de service',  'Gardez une continuité dans vos prestations avec des employés que vous connaissez.'],
            ['sparkles', 'Réservation plus fluide',  'Consultez les disponibilités de vos favoris et gagnez du temps lors de vos réservations.'],
            ['user',     'Service personnalisé',     'Profitez d\'une relation plus stable et plus personnalisée avec notre équipe.'],
            ['calendar', 'Confort mensuel',          'Une formule idéale pour les clients réguliers qui veulent un fonctionnement clair.'],
        ] as [$icon, $title, $text])
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-soft-sm p-5">
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-700 mb-3">
                    <x-ui.icon :name="$icon" class="w-4 h-4" />
                </div>
                <h3 class="text-sm font-bold text-slate-900">{{ $title }}</h3>
                <p class="mt-1.5 text-sm text-slate-600 leading-relaxed">{{ $text }}</p>
            </div>
        @endforeach
    </section>

    {{-- Comparatif --}}
    <section id="comparatif" class="rounded-2xl border border-slate-200/80 bg-white shadow-soft p-6 md:p-8">
        <div class="mb-6">
            <p class="ui-page-eyebrow !mt-0">Comparatif</p>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Standard vs Premium</h2>
            <p class="mt-1 text-sm text-slate-500">
                Choisissez la formule la plus adaptée à votre usage.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Standard --}}
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/40 p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Standard</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Pour les demandes ponctuelles</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-white border border-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                        Flexible
                    </span>
                </div>

                <p class="mt-3 text-sm text-slate-600">Idéal si vous réservez occasionnellement.</p>

                <ul class="mt-5 space-y-2 text-sm">
                    @foreach (['Réservation simple', 'Devis estimatif', 'Suivi du rendez-vous', 'Historique client', 'Attribution interne'] as $feat)
                        <li class="inline-flex items-start gap-2 w-full text-slate-700">
                            <x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-slate-400 flex-shrink-0" />
                            <span>{{ $feat }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Premium --}}
            <div class="relative rounded-2xl border-2 border-amber-300 bg-gradient-to-br from-amber-50 to-white p-6 shadow-soft-sm">
                <div class="absolute -top-3 left-6">
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                        <x-ui.icon name="star" class="w-3 h-3" />
                        Recommandé
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Premium</h3>
                        <p class="text-xs text-amber-700 mt-0.5">Pour les clients réguliers</p>
                    </div>
                    <span class="text-base font-bold text-amber-700">
                        <x-money :amount="(float) ($premiumPrice)" :decimals="0" /><span class="text-xs font-medium text-amber-600">/mois</span>
                    </span>
                </div>

                <p class="mt-3 text-sm text-slate-700">Plus de confort et de personnalisation.</p>

                <ul class="mt-5 space-y-2 text-sm">
                    <li class="inline-flex items-start gap-2 w-full text-slate-800">
                        <x-ui.icon name="check-circle" class="w-4 h-4 mt-0.5 text-emerald-600 flex-shrink-0" />
                        <span><strong>Tout</strong> ce qui est inclus dans Standard</span>
                    </li>
                    @foreach (['Choix des employés favoris', 'Disponibilités visibles', 'Réservation personnalisée', 'Expérience plus fluide'] as $feat)
                        <li class="inline-flex items-start gap-2 w-full text-slate-800">
                            <x-ui.icon name="check-circle" class="w-4 h-4 mt-0.5 text-emerald-600 flex-shrink-0" />
                            <span>{{ $feat }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="rounded-2xl border border-slate-200/80 bg-white shadow-soft p-6 md:p-8">
        <div class="mb-6">
            <p class="ui-page-eyebrow !mt-0">FAQ</p>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Questions fréquentes</h2>
        </div>

        <div class="space-y-2">
            @foreach ([
                ['Puis-je choisir le même employé à chaque fois ?', 'En Premium, vous pouvez sélectionner vos employés favoris. Nous faisons le maximum pour respecter votre préférence selon leurs disponibilités.'],
                ['Le paiement est-il mensuel ?', 'Oui, l\'offre Premium est une formule mensuelle simple et claire. Aucun engagement long.'],
                ['Puis-je revenir à l\'offre Standard ?', 'Oui, le passage d\'une formule à l\'autre est libre, selon les conditions de votre compte.'],
                ['Est-ce utile si je réserve souvent ?', 'Oui, c\'est précisément là que Premium prend toute sa valeur : plus de continuité, plus de personnalisation et plus de confort.'],
            ] as [$q, $a])
                <details class="group rounded-xl border border-slate-200/70 bg-white p-4 open:bg-slate-50/30 open:shadow-soft-sm transition">
                    <summary class="flex items-center justify-between cursor-pointer list-none">
                        <h3 class="text-sm font-semibold text-slate-900">{{ $q }}</h3>
                        <x-ui.icon name="plus" class="w-4 h-4 text-slate-400 group-open:rotate-45 transition" />
                    </summary>
                    <p class="mt-2.5 text-sm text-slate-600 leading-relaxed">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- CTA final --}}
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 shadow-soft p-8 md:p-10 text-white">
        <div class="absolute -top-16 -right-16 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-20 -left-20 h-72 w-72 rounded-full bg-white/5"></div>

        <div class="relative max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-100">Passez à un niveau supérieur</p>
            <h2 class="mt-2 text-2xl md:text-3xl font-bold tracking-tight">
                Une formule pensée pour les clients réguliers
            </h2>
            <p class="mt-3 text-amber-50 text-sm md:text-base leading-relaxed">
                Simplifiez votre organisation, retrouvez vos employés favoris et profitez d'une expérience plus personnalisée chaque mois.
            </p>

            <div class="mt-6 flex flex-wrap gap-2">
                @if($isPremium)
                    <span class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700">
                        <x-ui.icon name="check-circle" class="w-4 h-4" />
                        Vous êtes déjà Premium
                    </span>
                @else
                    <form method="POST" action="{{ route('premium.checkout') }}" class="contents">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-white text-amber-700 px-5 py-2.5 text-sm font-bold hover:bg-amber-50 transition shadow-soft-sm">
                            <x-ui.icon name="star" class="w-4 h-4" />
                            Passer en Premium
                            <x-ui.icon name="arrow-right" class="w-4 h-4" />
                        </button>
                    </form>
                @endif

                <a href="{{ route('booking.create') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/30 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/20 transition">
                    <x-ui.icon name="calendar" class="w-4 h-4" />
                    Réserver
                </a>
            </div>
        </div>
    </section>
</div>
