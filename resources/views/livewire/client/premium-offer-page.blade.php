<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">
    {{-- Hero --}}
    <section class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">
            <div class="p-8 md:p-10">
                <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                    Offre Premium mensuelle
                </span>

                <h1 class="mt-4 text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900">
                    Un service plus personnalisé, plus simple à gérer
                </h1>

                <p class="mt-4 text-base md:text-lg text-slate-600 leading-relaxed">
                    Passez à l’offre Premium mensuelle pour choisir vos employés favoris, consulter leurs disponibilités
                    et profiter d’une expérience plus fluide pour vos prestations régulières.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    @if($isPremium)
                    <span class="inline-flex items-center rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-3 text-sm font-semibold text-emerald-700">
                        Vous êtes déjà client Premium
                    </span>
                    @else
                    <button type="button"
                        class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-600 transition">
                        Passer en Premium
                    </button>
                    @endif

                    <a href="#comparatif"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Comparer les offres
                    </a>
                </div>
            </div>

            <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 text-white p-8 md:p-10 flex flex-col justify-center">
                <p class="text-sm uppercase tracking-[0.2em] text-slate-300">Tarif mensuel</p>
                <div class="mt-3 flex items-end gap-2">
                    <span class="text-5xl font-extrabold">{{ number_format($premiumPrice, 0, ',', ' ') }}€</span>
                    <span class="text-slate-300 mb-2">/ mois</span>
                </div>

                <p class="mt-4 text-slate-300">
                    Une formule claire pour les clients qui veulent plus de continuité, plus de confort
                    et une expérience plus personnalisée.
                </p>

                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li>• Choisissez vos employés favoris</li>
                    <li>• Consultez leurs disponibilités</li>
                    <li>• Réservez plus facilement vos prestations régulières</li>
                    <li>• Profitez d’un service plus personnalisé</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Pourquoi Premium --}}
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-bold text-slate-900">Même logique de service</h3>
            <p class="mt-2 text-sm text-slate-600">
                Gardez une continuité dans vos prestations avec des employés que vous connaissez déjà.
            </p>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-bold text-slate-900">Réservation plus fluide</h3>
            <p class="mt-2 text-sm text-slate-600">
                Consultez les disponibilités de vos favoris et gagnez du temps lors de vos prochaines réservations.
            </p>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-bold text-slate-900">Service personnalisé</h3>
            <p class="mt-2 text-sm text-slate-600">
                Profitez d’une relation plus stable et plus personnalisée avec notre équipe.
            </p>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-bold text-slate-900">Confort mensuel</h3>
            <p class="mt-2 text-sm text-slate-600">
                Une formule idéale pour les clients réguliers qui veulent un fonctionnement simple et clair.
            </p>
        </div>
    </section>

    {{-- Comparatif --}}
    <section id="comparatif" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 md:p-8">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900">Comparatif des offres</h2>
            <p class="text-sm text-slate-500 mt-1">
                Choisissez la formule la plus adaptée à votre manière de réserver.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Standard --}}
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-xl font-bold text-slate-900">Standard</h3>
                    <span class="inline-flex items-center rounded-full bg-white border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">
                        Flexible
                    </span>
                </div>

                <p class="mt-3 text-sm text-slate-600">
                    Idéal pour les demandes ponctuelles ou les clients qui veulent réserver simplement.
                </p>

                <ul class="mt-5 space-y-3 text-sm text-slate-700">
                    <li>• Réservation simple</li>
                    <li>• Devis estimatif</li>
                    <li>• Suivi du rendez-vous</li>
                    <li>• Historique client</li>
                    <li>• Attribution interne de l’employé</li>
                </ul>
            </div>

            {{-- Premium --}}
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-xl font-bold text-slate-900">Premium mensuel</h3>
                    <span class="inline-flex items-center rounded-full bg-white border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                        Recommandé
                    </span>
                </div>

                <p class="mt-3 text-sm text-slate-700">
                    Idéal pour les clients réguliers qui veulent plus de confort et de personnalisation.
                </p>

                <ul class="mt-5 space-y-3 text-sm text-slate-800">
                    <li>• Tout ce qui est inclus dans Standard</li>
                    <li>• Choix des employés favoris</li>
                    <li>• Disponibilités visibles</li>
                    <li>• Réservation plus personnalisée</li>
                    <li>• Expérience plus fluide au quotidien</li>
                </ul>

                <div class="mt-6">
                    <p class="text-sm text-amber-800 font-medium">À partir de {{ number_format($premiumPrice, 0, ',', ' ') }}€/mois</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 md:p-8">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900">Questions fréquentes</h2>
            <p class="text-sm text-slate-500 mt-1">
                Les réponses aux questions les plus utiles avant de passer au Premium.
            </p>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-900">Puis-je choisir le même employé à chaque fois ?</h3>
                <p class="text-sm text-slate-600 mt-2">
                    En Premium, vous pouvez sélectionner vos employés favoris. Nous faisons le maximum pour respecter votre préférence selon leurs disponibilités.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-900">Le paiement est-il mensuel ?</h3>
                <p class="text-sm text-slate-600 mt-2">
                    Oui, l’offre Premium est pensée comme une formule mensuelle simple et claire.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-900">Puis-je revenir à l’offre Standard ?</h3>
                <p class="text-sm text-slate-600 mt-2">
                    Oui, le passage d’une formule à l’autre pourra être géré selon les conditions de ton entreprise.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-900">Est-ce utile si je réserve souvent ?</h3>
                <p class="text-sm text-slate-600 mt-2">
                    Oui, c’est précisément là que l’offre Premium prend toute sa valeur : plus de continuité, plus de personnalisation et plus de confort.
                </p>
            </div>
        </div>
    </section>

    {{-- CTA final --}}
    <section class="bg-gradient-to-r from-amber-500 to-amber-600 rounded-3xl shadow-sm p-8 text-white">
        <div class="max-w-3xl">
            <p class="text-sm uppercase tracking-[0.2em] text-amber-100">Passez à un niveau supérieur</p>
            <h2 class="mt-3 text-3xl font-extrabold">
                Une formule pensée pour les clients réguliers
            </h2>
            <p class="mt-4 text-amber-50">
                Simplifiez votre organisation, retrouvez vos employés favoris et profitez d’une expérience plus personnalisée chaque mois.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                @if($isPremium)
                <span class="inline-flex items-center rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-3 text-sm font-semibold text-emerald-700">
                    Vous êtes déjà client Premium
                </span>
                @else
                <form method="POST" action="{{ route('premium.checkout') }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-600 transition">
                        Passer en Premium
                    </button>
                </form>
                @endif

                <a href="{{ route('booking.create') }}"
                    class="inline-flex items-center justify-center rounded-2xl border border-white/30 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 transition">
                    Réserver une prestation
                </a>
            </div>
        </div>
    </section>
</div>