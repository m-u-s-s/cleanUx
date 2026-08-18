{{--
    LA CASE « LOCATION » DU CATALOGUE.

    ELLE N'EXISTE PAS QUAND IL N'Y A RIEN À LOUER, et c'est le premier trait de son comportement.
    Une porte qui promet du choix devant une vitrine vide apprend au client que la plateforme
    annonce ce qu'elle ne sait pas faire — c'est le raisonnement que tient déjà le carrousel des
    secteurs pour les métiers non servables.

    ELLE NE RESSEMBLE PAS À UN SECTEUR, non plus, et c'est voulu : elle mène ailleurs. Un secteur
    ouvre le dock des métiers sans changer de page ; celle-ci quitte le parcours de commande pour
    un catalogue qui fonctionne autrement. Le format large et la mention « Nos véhicules » disent
    cette différence avant le clic.
--}}
<div>
    @if ($disponibles > 0)
        <a
            href="{{ route('location.catalogue') }}"
            wire:navigate
            data-cx-reveal
            class="group relative block overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-lg transition hover:shadow-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 dark:border-slate-700 sm:p-8"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-indigo-300">Nos véhicules</p>
                    <h3 class="mt-1 text-2xl font-black text-white sm:text-3xl">Location de voitures</h3>
                    <p class="mt-2 max-w-xl text-sm text-slate-300">
                        Réservez en ligne, réglez à l’agence. Assurance en option, kilométrage inclus,
                        retrait sur rendez&#8209;vous.
                    </p>

                    <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white">
                        {{-- LE NOMBRE EST UN FAIT VÉRIFIABLE, pas un argument : c'est exactement ce
                             que le catalogue montrera derrière. --}}
                        {{ $disponibles }} {{ $disponibles > 1 ? 'véhicules disponibles' : 'véhicule disponible' }}
                    </p>
                </div>

                <div class="shrink-0 text-5xl transition-transform duration-300 group-hover:scale-110 motion-reduce:transform-none" aria-hidden="true">
                    🚙
                </div>
            </div>
        </a>
    @endif
</div>
