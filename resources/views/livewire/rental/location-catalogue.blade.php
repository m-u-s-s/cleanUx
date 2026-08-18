{{--
    LA VITRINE DE LOCATION.

    Elle ne réutilise aucun composant du parcours de commande : celui-ci va du secteur au métier
    puis aux questions, alors qu'ici l'objet est visible dès la première seconde. Les deux écrans
    évoluent séparément, et c'est ce qui permet de toucher l'un sans risquer l'autre.

    LES FILTRES SORTENT DU PARC RÉELLEMENT DISPONIBLE. Proposer « monospace » sur un parc qui n'en a
    aucun apprendrait au client que la vitrine ment.
--}}
<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

        <header class="space-y-2">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Nos locations</p>
            <h1 class="text-3xl font-black text-slate-900 dark:text-white sm:text-4xl">Choisissez votre véhicule</h1>
            <p class="max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                Réservez en ligne, réglez à l’agence au moment du retrait. Le prix affiché est celui de la
                location seule&nbsp;; l’assurance complémentaire reste un choix, présenté à l’étape suivante.
            </p>
        </header>

        {{-- ── Les dates d'abord : elles changent la vitrine ─────────────────────── --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Départ</span>
                    <input type="datetime-local" wire:model.live.debounce.400ms="debut"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Retour</span>
                    <input type="datetime-local" wire:model.live.debounce.400ms="fin"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Catégorie</span>
                    <select wire:model.live="categorie"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                        <option value="">Toutes</option>
                        @foreach ($options['categories'] as $valeur)
                            <option value="{{ $valeur }}">{{ ucfirst($valeur) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Boîte</span>
                    <select wire:model.live="transmission"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                        <option value="">Toutes</option>
                        @foreach ($options['transmissions'] as $valeur)
                            <option value="{{ $valeur }}">{{ ucfirst($valeur) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Énergie</span>
                    <select wire:model.live="carburant"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                        <option value="">Toutes</option>
                        @foreach ($options['fuels'] as $valeur)
                            <option value="{{ $valeur }}">{{ ucfirst($valeur) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Places minimum</span>
                    <select wire:model.live="placesMin"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                        <option value="">Indifférent</option>
                        @foreach ([2, 4, 5, 7, 9] as $places)
                            <option value="{{ $places }}">{{ $places }} et plus</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Prix max / jour</span>
                    <input type="number" min="0" step="5" wire:model.live.debounce.500ms="prixMax"
                        placeholder="{{ $options['prix_max_cents'] > 0 ? number_format($options['prix_max_cents'] / 100, 0) : '' }}"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                </label>

                <div class="flex items-end">
                    <button type="button" wire:click="reinitialiserLesFiltres"
                        class="min-h-[44px] w-full rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        Réinitialiser
                    </button>
                </div>
            </div>
        </section>

        {{-- ── La vitrine ────────────────────────────────────────────────────────── --}}
        @if ($vehicules->isEmpty())
            {{--
                UNE VITRINE VIDE S'EXPLIQUE, elle ne se contente pas d'être vide.

                Le client vient de poser des dates ou des filtres : lui dire lesquels retirer est la
                seule information utile à cet instant.
            --}}
            <div class="rounded-3xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-600">
                <p class="text-lg font-semibold text-slate-700 dark:text-slate-200">Aucun véhicule sur ces critères</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Élargissez la période ou retirez un filtre&nbsp;: nos voitures sont peut-être déjà louées
                    sur ces dates.
                </p>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($vehicules as $vehicule)
                    @php($vignette = $vehicule->vignette())
                    <a href="{{ route('location.vehicule', ['vehicle' => $vehicule->id]) }}" wire:navigate
                       class="group flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900">

                        <div class="aspect-[4/3] w-full overflow-hidden bg-slate-100 dark:bg-slate-800">
                            @if ($vignette)
                                <img src="{{ $vignette->url() }}" alt="{{ $vehicule->nomComplet() }}"
                                     loading="lazy"
                                     class="h-full w-full object-cover transition duration-500 group-hover:scale-105 motion-reduce:transform-none">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-5xl" aria-hidden="true">🚗</div>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col gap-3 p-5">
                            <div>
                                <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ $vehicule->nomComplet() }}</h2>
                                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    {{ $vehicule->category }} · {{ $vehicule->transmission }} · {{ $vehicule->fuel }}
                                </p>
                            </div>

                            <ul class="flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300">
                                <li class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $vehicule->seats }} places</li>
                                <li class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $vehicule->doors }} portes</li>
                                <li class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $vehicule->luggage }} bagages</li>
                                @if ($vehicule->rotation360->isNotEmpty() || $vehicule->modele3d->isNotEmpty())
                                    <li class="rounded-full bg-indigo-100 px-2 py-1 font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Vue 360°</li>
                                @endif
                            </ul>

                            <div class="mt-auto flex items-end justify-between pt-2">
                                <div>
                                    <p class="text-2xl font-black text-slate-900 dark:text-white">
                                        {{ number_format($vehicule->daily_price_cents / 100, 2, ',', ' ') }}
                                        <span class="text-sm font-semibold">{{ $vehicule->currency }}</span>
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">par jour</p>
                                </div>
                                <span class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white dark:bg-indigo-600">Choisir</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
