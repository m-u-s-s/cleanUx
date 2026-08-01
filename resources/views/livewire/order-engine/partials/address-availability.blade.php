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
    <h2 id="adresse-titre" class="text-lg font-semibold text-slate-900">Où intervenons-nous ?</h2>
    <p class="mt-0.5 text-sm text-slate-500">
        L’adresse nous sert à trouver les professionnels les plus proches.
    </p>

    <label class="mt-4 block">
        <span class="sr-only">Adresse de l’intervention</span>
        <input
            type="text"
            wire:model.live.debounce.600ms="address"
            autocomplete="street-address"
            placeholder="Rue, numéro, code postal"
            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
            aria-describedby="adresse-etat"
        >
    </label>

    <div id="adresse-etat" class="mt-3" aria-live="polite">

        <p wire:loading wire:target="address" class="text-sm text-slate-500">Recherche des professionnels…</p>

        <div wire:loading.remove wire:target="address">

            @if ($addressUnresolved)
                {{-- On le dit, plutôt que de laisser un champ muet — et on n'empêche pas de continuer. --}}
                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Nous n’avons pas situé cette adresse. Vous pouvez continuer : elle sera confirmée
                    avec le professionnel.
                </p>

            @elseif ($this->availability())
                @php $snapshot = $this->availability(); @endphp

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
