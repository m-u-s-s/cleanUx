{{--
    L'adresse, et ce qu'elle débloque.

    Posée en FIN de parcours, à dessein : elle récompense le client d'être allé jusque-là par une
    information qui le rassure, au lieu de le filtrer à l'entrée. Un formulaire d'adresse en
    premier écran, c'est un péage avant d'avoir montré quoi que ce soit.

    Et ce qu'on affiche est VRAI ou n'est pas affiché. La confiance vient de la disponibilité
    visible, pas d'un badge décoratif — mais un compte inventé se retourne contre la marque au
    premier client qui attend.
--}}
<section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="adresse-titre">
    <h2 id="adresse-titre" class="text-lg font-semibold text-slate-900">
        {{ $this->estUnTrajet ? 'Votre trajet' : 'Où intervenons-nous ?' }}
    </h2>
    <p class="mt-0.5 text-sm text-slate-500">
        {{ $this->estUnTrajet
            ? 'Le départ et l’arrivée sont demandés dans le formulaire ci-dessus.'
            : 'L’adresse nous sert à trouver les professionnels les plus proches.' }}
    </p>

    {{--
        SUR UN TRAJET, L'ADRESSE EST DÉJÀ POSÉE — c'est la question de DÉPART.

        La redemander ici donnerait à croire qu'on en attend une seconde, différente, et le client
        finirait par saisir deux lieux pour la même prise en charge. Ce bloc-ci ne montre donc que
        le RÉSULTAT : les deux points et la route entre eux.
    --}}
    @if ($this->estUnTrajet)
        <div class="mt-4 space-y-2">
            <p class="flex items-start gap-2 text-sm text-slate-700">
                <span aria-hidden="true" class="mt-0.5 text-slate-400">◎</span>
                <span>{{ $address ?: 'Point de départ à renseigner ci-dessus.' }}</span>
            </p>
            <p class="flex items-start gap-2 text-sm text-slate-700">
                <span aria-hidden="true" class="mt-0.5 text-slate-400">⚑</span>
                <span>{{ $this->draft()->dropoff_address ?: 'Point d’arrivée à renseigner ci-dessus.' }}</span>
            </p>

            @if ($this->route)
                {{-- La distance est annoncée AVANT le paiement : un tarif au kilomètre découvert à
                     l'arrivée est exactement ce qu'on reproche aux taxis. --}}
                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <span class="font-semibold tabular-nums">{{ str_replace('.', ',', (string) $this->route['distance_km']) }} km</span>
                    @if ($this->route['duration_min'])
                        · environ {{ $this->route['duration_min'] }} min de trajet
                    @endif
                    @if ($this->route['approximatif'])
                        <span class="mt-1 block text-xs text-slate-500">
                            Distance estimée à vol d’oiseau : le trajet réel sera un peu plus long.
                        </span>
                    @endif
                </p>
            @endif
        </div>
    @endif

    @unless ($this->estUnTrajet)

    {{--
        LE CARNET DE LIEUX (E2), quand il y en a un.

        Il vient AVANT le champ libre, parce que c'est la réponse la plus rapide pour qui revient :
        retaper l'adresse, l'étage et le code à chaque commande est ce qui fait se tromper une fois
        sur cinq — et envoie un professionnel à la mauvaise porte.
    --}}
    @if ($this->savedPlaces->isNotEmpty())
    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($this->savedPlaces as $lieu)
        <button
            type="button"
            wire:click="choisirLeLieu({{ $lieu->id }})"
            class="rounded-full border px-3 py-1.5 text-sm font-semibold transition {{ $clientPlaceId === $lieu->id ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-50' }}"
        >
            {{ $lieu->label }}
        </button>
        @endforeach
    </div>
    @endif

    {{-- Association EXPLICITE plutôt qu'imbrication : un `for`/`id` survit à un remaniement du
         balisage, alors qu'une étiquette qui enveloppe son champ perd le lien dès qu'on insère un
         conteneur entre les deux. --}}
    <label for="adresse-intervention" class="mt-4 block">
        <span class="sr-only">Adresse de l’intervention</span>
        <input
            id="adresse-intervention"
            type="text"
            wire:model.live.debounce.600ms="address"
            autocomplete="street-address"
            placeholder="Rue, numéro, code postal"
            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
            aria-describedby="adresse-etat"
        >
    </label>

    {{--
        Les suggestions, et le raccourci « ma position ».

        Le champ nu acceptait d'avance les fautes de frappe, et une faute de frappe fait échouer le
        géocodage EN SILENCE : plus de preuve de disponibilité, et un professionnel envoyé à la
        mauvaise porte. La plateforme sert déjà des suggestions d'adresse ailleurs — l'application
        mobile s'en sert ; c'était l'écran le plus rentable du produit qui s'en passait.
    --}}
    @if (count($this->addressSuggestions))
        <ul class="mt-2 overflow-hidden rounded-xl border border-slate-200" role="listbox"
            aria-label="Adresses proposées">
            @foreach ($this->addressSuggestions as $suggestion)
                <li role="option" aria-selected="false" wire:key="sugg-{{ md5($suggestion->description) }}">
                    <button type="button"
                        wire:click="chooseAddressSuggestion(
                            @js($suggestion->description),
                            @js($suggestion->latitude),
                            @js($suggestion->longitude)
                        )"
                        class="flex min-h-[44px] w-full items-center px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50">
                        {{ $suggestion->description }}
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    {{--
        La géolocalisation reste côté navigateur : le serveur ne reçoit que deux nombres, et
        seulement si le client accepte. Le bouton disparaît quand l'API n'existe pas, plutôt que
        d'offrir une action qui échouerait.
    --}}
    <div x-data="{ supported: 'geolocation' in navigator, busy: false, denied: false }" x-cloak>
        <button type="button" x-show="supported" x-bind:disabled="busy"
            x-on:click="
                busy = true; denied = false;
                navigator.geolocation.getCurrentPosition(
                    (pos) => { busy = false; $wire.useMyPosition(pos.coords.latitude, pos.coords.longitude) },
                    () => { busy = false; denied = true },
                    { enableHighAccuracy: true, timeout: 8000 }
                )
            "
            class="mt-3 inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
            <span aria-hidden="true">◎</span>
            <span x-text="busy ? 'Localisation…' : 'Utiliser ma position'">Utiliser ma position</span>
        </button>

        {{-- Un refus n'est pas une panne : on dit quoi faire ensuite. --}}
        <p x-show="denied" x-cloak class="mt-2 text-sm text-slate-600">
            Nous n’avons pas pu vous localiser. Saisissez l’adresse ci-dessus, cela fonctionne aussi bien.
        </p>
    </div>
    @endunless

    {{-- La preuve de disponibilité vaut pour les DEUX parcours : elle se calcule sur le point de
         prise en charge, que celui-ci vienne du champ ci-dessus ou de la question de départ. --}}
    <div id="adresse-etat" class="mt-3" aria-live="polite">

        <p wire:loading wire:target="address" class="text-sm text-slate-500">Recherche des professionnels…</p>

        <div wire:loading.remove wire:target="address">

            @if ($addressUnresolved)
                {{-- On le dit, plutôt que de laisser un champ muet — et on n'empêche pas de continuer. --}}
                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Nous n’avons pas situé cette adresse. Vous pouvez continuer : elle sera confirmée
                    avec le professionnel.
                </p>

            @elseif ($this->availability)
                @php $snapshot = $this->availability; @endphp

                @if ($snapshot->hasProviders())
                    {{-- Le chiffre est réel : il vient des prestataires du métier dont on connaît la position. --}}
                    <p class="flex flex-wrap items-baseline gap-x-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {{-- Compte et mot dans une seule expression : un retour à la ligne entre les
                             deux se lit « deux … professionnels » au lecteur d'écran. --}}
                        <span class="font-semibold tabular-nums">{{ trans_choice(':count professionnel|:count professionnels', $snapshot->providerCount, ['count' => $snapshot->providerCount]) }}</span>
                        <span>à moins de {{ $snapshot->radiusKm() }} km</span>

                        @if ($snapshot->earliestAt)
                            <span class="w-full text-emerald-800">
                                Première intervention possible
                                {{ $snapshot->earliestAt->isToday() ? "aujourd'hui" : $snapshot->earliestAt->translatedFormat('l j F') }}
                                à {{ $snapshot->earliestAt->format('H\hi') }}.
                            </span>
                        @endif
                    </p>

                @elseif (! $snapshot->isTrustworthy())
                    {{--
                        Aucun prestataire n'a de position connue : on ne peut RIEN affirmer sur la
                        proximité. Se taire vaut mieux qu'annoncer un chiffre invérifiable.
                    --}}
                    <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Nous cherchons un professionnel disponible dans votre secteur.
                    </p>

                @else
                    {{--
                        L'impasse — et jamais d'écran mort. Trois portes restent ouvertes : élargir,
                        changer de métier, ou être prévenu.
                    --}}
                    <div class="space-y-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p class="font-medium">
                            Aucun professionnel disponible à moins de {{ $snapshot->radiusKm() }} km pour le moment.
                        </p>

                        @if ($snapshot->widerRadiusCount > 0)
                            <p>
                                En revanche, {{ $snapshot->widerRadiusCount }}
                                professionnel{{ $snapshot->widerRadiusCount > 1 ? 's' : '' }}
                                {{ $snapshot->widerRadiusCount > 1 ? 'interviennent' : 'intervient' }}
                                jusqu’à {{ round($snapshot->widerRadiusM / 1000) }} km — avec un déplacement
                                un peu plus long.
                            </p>
                        @endif

                        @if ($snapshot->nearbyTrades)
                            <div>
                                <p class="mb-1.5">Ces métiers sont couverts près de chez vous :</p>
                                <ul class="flex flex-wrap gap-2">
                                    @foreach ($snapshot->nearbyTrades as $neighbour)
                                        <li>
                                            <button type="button" wire:click="selectTrade({{ $neighbour['trade_id'] }})"
                                                class="min-h-[40px] rounded-lg border border-amber-300 bg-white px-3 text-sm font-medium text-amber-900 hover:border-amber-500">
                                                {{ $neighbour['name'] }}
                                                <span class="tabular-nums text-amber-700">({{ $neighbour['provider_count'] }})</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <p class="text-amber-800">
                            Vous pouvez aussi continuer : nous vous prévenons dès qu’un professionnel se libère.
                        </p>
                    </div>
                @endif
            @endif
        </div>
    </div>
</section>
