{{--
    LES TROIS FAÇONS DE COMMANDER — la première question du parcours.

    Ce sont des INTENTIONS différentes, pas trois réglages du même formulaire : « j'ai une fuite
    maintenant » et « je planifie un grand nettoyage en mai » ne cherchent pas le même catalogue.
    L'application mobile posait déjà la question en premier ; le web arrivait sur le catalogue
    complet, et l'immédiat ne se découvrait qu'après avoir choisi un métier — parfois pour apprendre
    que ce métier ne le permet pas.

    LES CARTES SONT DES BOUTONS RADIO, pas des liens : le choix reste modifiable sans quitter la
    page, et un lecteur d'écran annonce un groupe de trois options dont une est retenue.
--}}
@php
    $cartes = [
        [
            'mode' => null,
            'titre' => 'Tous les services',
            'detail' => 'Parcourir le catalogue complet',
            'icone' => '🧭',
            'test' => 'mode-card-all',
        ],
        [
            'mode' => 'asap',
            'titre' => 'Intervention immédiate',
            'detail' => 'Un professionnel prend la route maintenant',
            'icone' => '⚡',
            'test' => 'mode-card-asap',
        ],
        [
            'mode' => 'scheduled',
            'titre' => 'Prendre rendez-vous',
            'detail' => 'Vous choisissez le jour et l’heure',
            'icone' => '📅',
            'test' => 'mode-card-scheduled',
        ],
        [
            'mode' => 'bundle',
            'titre' => 'Plusieurs services',
            'detail' => 'Un seul chantier, un seul paiement',
            'icone' => '🧩',
            'test' => 'mode-card-bundle',
        ],
    ];
@endphp

<div role="radiogroup" aria-label="Comment souhaitez-vous commander ?"
    class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ($cartes as $carte)
        @php $retenue = $intendedMode === $carte['mode']; @endphp

        <button type="button"
            wire:click="chooseIntent({{ $carte['mode'] === null ? 'null' : "'".$carte['mode']."'" }})"
            wire:key="intent-{{ $carte['test'] }}"
            role="radio" aria-checked="{{ $retenue ? 'true' : 'false' }}"
            data-test="{{ $carte['test'] }}"
            @class([
                'rounded-2xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500',
                'border-blue-600 bg-blue-50 ring-1 ring-blue-600' => $retenue,
                'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50' => ! $retenue,
            ])>
            <span class="text-xl" aria-hidden="true">{{ $carte['icone'] }}</span>
            <span class="mt-2 block text-sm font-semibold text-slate-900">{{ $carte['titre'] }}</span>
            <span class="mt-0.5 block text-xs leading-snug text-slate-500">{{ $carte['detail'] }}</span>
        </button>
    @endforeach
</div>

@if ($this->intentIsNarrowing)
    {{--
        DIRE CE QUE LE CLIENT VOIT. Un catalogue filtré sans explication ressemble à un catalogue
        vide, et c'est la plateforme qu'on soupçonne, pas le filtre.
    --}}
    <p class="mt-3 text-xs text-slate-500" data-test="mode-narrowing-notice">
        @if ($intendedMode === 'asap')
            Seuls les métiers ouverts à l’intervention immédiate
            @if ($serviceZoneId) dans votre zone @endif
            sont affichés.
        @else
            Seuls les métiers qui se combinent au sein d’un même chantier sont affichés.
        @endif
    </p>
@endif
