<x-guest-layout>

<div class="bg-slate-50 text-slate-900">
    {{-- HERO --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.15),transparent_30%),radial-gradient(circle_at_bottom_left,rgba(14,165,233,0.15),transparent_30%)]"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div class="text-white">
                    <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-wide uppercase">
                        Entreprise de nettoyage moderne
                    </span>

                    <h1 class="mt-6 text-4xl md:text-5xl lg:text-6xl font-extrabold leading-tight tracking-tight">
                        Un service de nettoyage professionnel,
                        <span class="text-amber-300">simple à réserver</span>
                        et facile à suivre
                    </h1>

                    <p class="mt-6 text-lg text-slate-200 max-w-2xl leading-relaxed">
                        Maison, appartement, bureaux ou interventions en profondeur :
                        profitez d’une équipe réelle, d’une organisation claire et d’une expérience digitale fluide.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('booking.create') }}"
                           class="inline-flex items-center justify-center rounded-2xl bg-sky-500 px-6 py-3.5 text-sm font-semibold text-white hover:bg-sky-600 transition">
                            Réserver une prestation
                        </a>

                        <a href="{{ route('premium.offer') }}"
                           class="inline-flex items-center justify-center rounded-2xl border border-white/20 bg-white/10 px-6 py-3.5 text-sm font-semibold text-white hover:bg-white/15 transition">
                            Découvrir l’offre Premium
                        </a>
                    </div>

                    <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <p class="font-semibold text-white">Réservation rapide</p>
                            <p class="mt-1 text-slate-300">Parcours simple et clair</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <p class="font-semibold text-white">Suivi rassurant</p>
                            <p class="mt-1 text-slate-300">Statuts et historique client</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <p class="font-semibold text-white">Service premium</p>
                            <p class="mt-1 text-slate-300">Option mensuelle personnalisée</p>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="rounded-[2rem] bg-white shadow-2xl border border-slate-200 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 bg-slate-50">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-slate-500">Aperçu du service</p>
                                    <h3 class="text-xl font-bold text-slate-900 mt-1">Expérience client claire</h3>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700">
                                    Disponible
                                </span>
                            </div>
                        </div>

                        <div class="p-6 space-y-4">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm text-slate-500">Prochaine intervention</p>
                                        <p class="text-lg font-bold text-slate-900 mt-1">Nettoyage standard</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full bg-sky-50 border border-sky-200 px-3 py-1 text-xs font-semibold text-sky-700">
                                        Confirmée
                                    </span>
                                </div>
                                <p class="mt-3 text-sm text-slate-600">Mardi 10:00 — Bruxelles</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <p class="text-sm text-slate-500">Réservation</p>
                                    <p class="text-lg font-bold text-slate-900 mt-1">En 4 étapes</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <p class="text-sm text-slate-500">Devis estimatif</p>
                                    <p class="text-lg font-bold text-slate-900 mt-1">Visible en direct</p>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-semibold text-amber-800">Option Premium mensuelle</p>
                                <p class="mt-1 text-sm text-amber-700">
                                    Choix des employés favoris, disponibilités visibles et expérience plus personnalisée.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section class="py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">Comment ça marche</p>
                <h2 class="mt-3 text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900">
                    Une organisation simple, claire et professionnelle
                </h2>
                <p class="mt-4 text-slate-600">
                    Nous avons pensé l’expérience pour qu’elle soit fluide côté client et efficace côté équipe.
                </p>
            </div>

            <div class="mt-10 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <div class="text-sky-600 font-extrabold text-2xl">1</div>
                    <h3 class="mt-3 text-lg font-bold text-slate-900">Choisissez votre service</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Sélectionnez le type de prestation, le lieu, la fréquence et les besoins principaux.
                    </p>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <div class="text-sky-600 font-extrabold text-2xl">2</div>
                    <h3 class="mt-3 text-lg font-bold text-slate-900">Indiquez vos préférences</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Ajoutez vos options, votre adresse, vos commentaires et vos contraintes d’accès.
                    </p>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <div class="text-sky-600 font-extrabold text-2xl">3</div>
                    <h3 class="mt-3 text-lg font-bold text-slate-900">Planifiez le créneau</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        En standard, nous gérons l’attribution. En Premium, vous pouvez choisir vos favoris.
                    </p>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <div class="text-sky-600 font-extrabold text-2xl">4</div>
                    <h3 class="mt-3 text-lg font-bold text-slate-900">Suivez la prestation</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Retrouvez vos rendez-vous, votre historique et les informations utiles dans votre espace client.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- OFFERS --}}
    <section class="pb-16 md:pb-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-600">Nos offres</p>
                <h2 class="mt-3 text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900">
                    Deux niveaux de service, selon votre manière de réserver
                </h2>
                <p class="mt-4 text-slate-600">
                    Une formule simple pour les besoins classiques, et une formule Premium pour les clients réguliers.
                </p>
            </div>

            <div class="mt-10 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-8">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-2xl font-bold text-slate-900">Standard</h3>
                        <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">
                            Flexible
                        </span>
                    </div>

                    <p class="mt-4 text-slate-600">
                        Idéal pour les demandes ponctuelles ou les clients qui veulent réserver rapidement.
                    </p>

                    <ul class="mt-6 space-y-3 text-sm text-slate-700">
                        <li>• Réservation simple et rapide</li>
                        <li>• Devis estimatif visible</li>
                        <li>• Attribution interne de l’employé</li>
                        <li>• Suivi du rendez-vous</li>
                        <li>• Historique client</li>
                    </ul>

                    <div class="mt-8">
                        <a href="{{ route('booking.create') }}"
                           class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 transition">
                            Réserver en Standard
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-[2rem] border border-amber-200 shadow-sm p-8 relative overflow-hidden">
                    <div class="absolute top-0 right-0 m-6">
                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                            Recommandé
                        </span>
                    </div>

                    <div class="max-w-xl">
                        <h3 class="text-2xl font-bold text-slate-900">Premium mensuel</h3>

                        <p class="mt-4 text-slate-700">
                            Pensé pour les clients réguliers qui veulent plus de confort, plus de personnalisation et plus de continuité.
                        </p>

                        <div class="mt-6 flex items-end gap-2">
                            <span class="text-4xl font-extrabold text-slate-900">29€</span>
                            <span class="text-slate-500 mb-1">/ mois</span>
                        </div>

                        <ul class="mt-6 space-y-3 text-sm text-slate-800">
                            <li>• Tout ce qui est inclus dans Standard</li>
                            <li>• Choix des employés favoris</li>
                            <li>• Visibilité sur leurs disponibilités</li>
                            <li>• Réservation plus personnalisée</li>
                            <li>• Expérience plus fluide au quotidien</li>
                        </ul>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ route('premium.offer') }}"
                               class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-600 transition">
                                Découvrir Premium
                            </a>

                            <a href="{{ route('booking.create') }}"
                               class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                Réserver maintenant
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SERVICES --}}
    <section class="py-16 md:py-20 bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">Prestations</p>
                <h2 class="mt-3 text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900">
                    Des services pensés pour le quotidien comme pour les besoins spécifiques
                </h2>
            </div>

            <div class="mt-10 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="rounded-3xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-900">Nettoyage standard</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Pour l’entretien classique de votre maison, appartement ou espace de travail.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-900">Nettoyage en profondeur</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Pour un besoin plus poussé avec davantage de détails et d’options ciblées.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-900">Fin de bail / chantier</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Pour les situations qui demandent une intervention plus complète et structurée.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-900">Bureaux & professionnels</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Une solution adaptée aux structures qui veulent un service sérieux et bien organisé.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- TESTIMONIALS --}}
    <section class="py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-600">Avis clients</p>
                <h2 class="mt-3 text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900">
                    Une expérience pensée pour inspirer confiance
                </h2>
            </div>

            <div class="mt-10 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <p class="text-slate-700 leading-relaxed">
                        “Réservation simple, suivi clair, et intervention très propre. On sent que l’équipe est bien organisée.”
                    </p>
                    <p class="mt-4 text-sm font-semibold text-slate-900">Sophie, Anderlecht</p>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <p class="text-slate-700 leading-relaxed">
                        “Le dashboard client est très rassurant. On sait rapidement où en est la demande et qui intervient.”
                    </p>
                    <p class="mt-4 text-sm font-semibold text-slate-900">Client régulier, Uccle</p>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <p class="text-slate-700 leading-relaxed">
                        “La formule Premium est vraiment pratique pour garder les mêmes habitudes et réserver plus facilement.”
                    </p>
                    <p class="mt-4 text-sm font-semibold text-slate-900">Cabinet médical, Bruxelles</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="pb-16 md:pb-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-[2rem] bg-gradient-to-r from-sky-600 to-sky-700 text-white p-8 md:p-10 shadow-sm">
                <div class="max-w-3xl">
                    <p class="text-sm uppercase tracking-[0.2em] text-sky-100">Prêt à planifier votre prestation</p>
                    <h2 class="mt-3 text-3xl md:text-4xl font-extrabold tracking-tight">
                        Réservez facilement ou découvrez l’expérience Premium
                    </h2>
                    <p class="mt-4 text-sky-50">
                        Un service de nettoyage professionnel, une gestion claire et une expérience pensée pour durer.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('booking.create') }}"
                           class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-sky-700 hover:bg-sky-50 transition">
                            Réserver maintenant
                        </a>

                        <a href="{{ route('premium.offer') }}"
                           class="inline-flex items-center justify-center rounded-2xl border border-white/30 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 transition">
                            Voir l’offre Premium
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>


    
</x-guest-layout>