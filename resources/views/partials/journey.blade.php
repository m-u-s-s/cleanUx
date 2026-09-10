{{-- ============================================================
     Brio — « Le Film » : de la réservation à la poignée de main.

     14 plans photoréalistes, scrubbés au scroll, rendus dans un moteur WebGL
     (resources/js/journey-film.js -> journey-film-core.js). Les frames vivent
     dans public/images/journey-film/ et se refabriquent avec
     scripts/journey-film/construire-les-frames.sh.

     TOUT LE TEXTE EST ICI, PAS DANS LA VIDÉO : net à tout DPI, traduit, lu par
     les lecteurs d'écran, et modifiable sans regénérer un plan.

     Replis : pas de WebGL -> canvas 2D · pas d'AVIF / réseau -> poster ·
     prefers-reduced-motion -> le <ol> ci-dessous, seul visuel.
     ============================================================ --}}
@php
    $nombreDePlans = 14;
@endphp

<section class="cx-film"
         data-cx-film
         aria-labelledby="cx-film-titre">

    {{-- LA PISTE : c'est ELLE qui porte les 800vh de scroll, pas la section.
         Poser la hauteur sur la section ferait passer le bloc de téléchargement
         SOUS la scène collante au lieu de le suivre. --}}
    <div class="cx-film__piste" data-cx-film-piste>

    {{-- L'en-tête, toujours lisible, même sans JS. --}}
    <div class="cx-film__intro">
        <p class="cx-film__eyebrow">{{ __('vitrine.film.eyebrow') }}</p>
        <h2 id="cx-film-titre" class="cx-film__titre">{{ __('vitrine.film.titre') }}</h2>
        <p class="cx-film__sous-titre">{{ __('vitrine.film.sous_titre') }}</p>
    </div>

    {{-- Le repli universel : la liste des étapes. Elle EST le contenu accessible
         de la section — le film n'en est que la mise en scène. --}}
    <ol class="cx-film__etapes">
        @for ($i = 1; $i <= $nombreDePlans; $i++)
            <li>
                <b>{{ __('vitrine.film.plans.'.$i.'.titre') }}</b>
                <span>{{ __('vitrine.film.plans.'.$i.'.detail') }}</span>
            </li>
        @endfor
    </ol>

    {{-- La scène qui se fige pendant que le scroll fait avancer le film. --}}
    <div class="cx-film__scene">
        <canvas class="cx-film__canvas" data-cx-film-canvas aria-hidden="true"></canvas>

        {{-- Le poster : visible tant que la première frame n'est pas décodée, et
             visuel définitif des navigateurs sans AVIF. --}}
        <picture class="cx-film__poster" aria-hidden="true">
            {{-- Versionne : le poster garde son nom d'un tournage a l'autre. --}}
            <source srcset="{{ \App\Support\Vitrine\FilmDuParcours::asset('poster.webp') }}" type="image/webp">
            <img src="{{ \App\Support\Vitrine\FilmDuParcours::asset('poster.jpg') }}" alt="" width="1280" height="720"
                 loading="lazy" decoding="async">
        </picture>

        <div class="cx-film__voile" aria-hidden="true"></div>

        {{-- Les légendes : une par plan, celle du plan courant est révélée par le JS.
             aria-live off : le <ol> ci-dessus porte déjà tout le texte pour les
             lecteurs d'écran, l'annoncer deux fois serait du bruit. --}}
        <div class="cx-film__legendes" aria-hidden="true">
            @for ($i = 1; $i <= $nombreDePlans; $i++)
                <p class="cx-film__legende @if($i === 1) is-active @endif" data-cx-film-chapitre>
                    <b>{{ __('vitrine.film.plans.'.$i.'.titre') }}</b>
                    <span>{{ __('vitrine.film.plans.'.$i.'.detail') }}</span>
                </p>
            @endfor
        </div>

        <div class="cx-film__barre" aria-hidden="true"><i></i></div>

        <div class="cx-film__puces" aria-hidden="true">
            @for ($i = 1; $i <= $nombreDePlans; $i++)
                <span class="cx-film__puce @if($i === 1) is-active @endif" data-cx-film-puce></span>
            @endfor
        </div>
    </div>

    </div>{{-- /.cx-film__piste --}}

    {{-- Le scroll continue, la piste est finie : l'invitation à emporter Brio. --}}
    <x-apps.telechargement />
</section>
