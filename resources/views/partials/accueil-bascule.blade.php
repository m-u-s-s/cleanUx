{{-- ============================================================
     La bascule client / prestataire.

     Elle pilote TOUTE la page sous le héros. Elle colle sous la barre de
     navigation : le visiteur qui a descendu la moitié de la page doit pouvoir
     changer de côté sans remonter.

     Le choix survit au rechargement (localStorage) et se partage par lien
     (#client / #prestataire) : un artisan à qui l'on envoie la page doit
     arriver sur son côté, pas sur celui du client.
     ============================================================ --}}
<div class="cx-bascule" role="tablist" aria-label="{{ __('vitrine.accueil.bascule.aide') }}">
    <div class="cx-bascule__piste">
        {{-- Le curseur glisse sous l'onglet choisi : le mouvement dit le changement
             mieux qu'un changement de couleur. --}}
        <span class="cx-bascule__curseur" aria-hidden="true"
              :style="cote === 'prestataire' && 'transform: translateX(100%)'"></span>

        @foreach (['client', 'prestataire'] as $cle)
            <button type="button"
                    class="cx-bascule__onglet"
                    role="tab"
                    id="cx-bascule-{{ $cle }}"
                    aria-controls="cx-cote-{{ $cle }}"
                    :class="cote === '{{ $cle }}' && 'is-choisi'"
                    :aria-selected="cote === '{{ $cle }}' ? 'true' : 'false'"
                    @click="choisir('{{ $cle }}')">
                <x-ui.icon :name="$cle === 'client' ? 'sparkles' : 'wrench'" class="h-4 w-4" />
                {{ __('vitrine.accueil.bascule.'.$cle) }}
            </button>
        @endforeach
    </div>

    <p class="cx-bascule__aide">{{ __('vitrine.accueil.bascule.aide') }}</p>
</div>
