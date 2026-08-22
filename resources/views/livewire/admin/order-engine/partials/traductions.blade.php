{{--
    LES TRADUCTIONS D'UN OBJET DU CATALOGUE, REPLIÉES.

    Repliées parce que l'on édite le français neuf fois sur dix ; mais le badge, lui, reste visible :
    un trou de traduction se découvre sinon en production, par un client qui ne comprend pas ce
    qu'on lui propose.

    Une seule partielle pour le secteur ET le métier. Deux copies du même formulaire auraient fini
    par diverger — l'une gagnant une langue, l'autre non — et personne ne l'aurait vu avant qu'un
    libellé manque dans un seul des deux écrans.

    @param $objet  un modèle qui implémente TranslatesCatalogLabels
    @param $type   'sector' ou 'trade' — ce que `saveTranslation()` attend
    @param $champs ['name' => 'Nom', …] les champs traduisibles et leur libellé d'écran
--}}
@php($manquantes = $objet->missingLocales(array_keys($champs)))

<details class="mt-3 border-t border-slate-100 pt-3">
    <summary class="cursor-pointer text-sm text-slate-600">
        Traductions
        @if ($manquantes)
            <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                {{ count($manquantes) }} manquante{{ count($manquantes) > 1 ? 's' : '' }}
            </span>
        @else
            <span class="ml-1 text-xs text-emerald-700">complètes</span>
        @endif
    </summary>

    <div class="mt-3 space-y-4">
        @foreach ($champs as $champ => $libelle)
            @continue(blank($objet->{$champ}))

            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $libelle }}</p>

                @foreach ($this->translationLocales() as $code => $langue)
                    <div>
                        <label for="t-{{ $type }}-{{ $objet->id }}-{{ $champ }}-{{ $code }}"
                            class="block text-xs font-medium text-slate-500">{{ $langue }}</label>
                        <input id="t-{{ $type }}-{{ $objet->id }}-{{ $champ }}-{{ $code }}" type="text"
                            value="{{ $objet->translations->where('field', $champ)->firstWhere('locale', $code)?->value }}"
                            placeholder="{{ $objet->{$champ} }}"
                            wire:change="saveTranslation('{{ $type }}', {{ $objet->id }}, '{{ $code }}', '{{ $champ }}', $event.target.value)"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-slate-900 focus:ring-0">
                    </div>
                @endforeach
            </div>
        @endforeach

        <p class="text-xs text-slate-400">
            Laisser vide affiche le libellé français : mieux vaut la mauvaise langue qu’un blanc.
        </p>
    </div>
</details>
