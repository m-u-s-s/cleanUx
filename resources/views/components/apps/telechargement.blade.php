@php
    use App\Support\Apps\LiensApplications;
    use App\Support\Apps\QrDeTelechargement;

    /*
     * Le bloc de telechargement, en bout de film. Il ne se dessine pas s'il n'a rien a dire :
     * une carte sans lien `https` publiable n'existe pas, et zero carte = zero section.
     */
    $cartes = [];

    foreach (LiensApplications::APPLICATIONS as $application) {
        $etat = LiensApplications::pour($application);

        if (! $etat['visible']) {
            continue;
        }

        $cartes[] = [
            'cle' => $application,
            'titre' => __('vitrine.apps.'.$application.'.titre'),
            'accroche' => __('vitrine.apps.'.$application.'.accroche'),
            'logo' => asset('images/brand/brio-'.($application === LiensApplications::CLIENT ? 'client' : 'provider').'-dark-256.png'),
            'liens' => $etat['liens'],
            'qr' => LiensApplications::qrActif() ? QrDeTelechargement::pour($application, 132) : null,
        ];
    }

    $libelles = [
        'ios' => ['magasin' => 'App Store', 'action' => __('vitrine.apps.liens.ios')],
        'android' => ['magasin' => 'Google Play', 'action' => __('vitrine.apps.liens.android')],
        'smartlink' => ['magasin' => 'Brio', 'action' => __('vitrine.apps.liens.smartlink')],
    ];
@endphp

@if($cartes !== [])
    <section id="telecharger" class="cx-apps" aria-labelledby="cx-apps-titre">
        <div class="cx-apps__inner">
            <p class="cx-apps__eyebrow">{{ __('vitrine.apps.eyebrow') }}</p>
            <h3 id="cx-apps-titre" class="cx-apps__titre">{{ __('vitrine.apps.titre') }}</h3>
            <p class="cx-apps__sous-titre">{{ __('vitrine.apps.sous_titre') }}</p>

            <div class="cx-apps__grille">
                @foreach($cartes as $carte)
                    <article class="cx-apps__carte">
                        <img class="cx-apps__logo" src="{{ $carte['logo'] }}" alt="" width="72" height="72" loading="lazy" decoding="async">

                        <h4 class="cx-apps__carte-titre">{{ $carte['titre'] }}</h4>
                        <p class="cx-apps__carte-accroche">{{ $carte['accroche'] }}</p>

                        <ul class="cx-apps__liens">
                            @foreach($carte['liens'] as $destination => $lien)
                                <li>
                                    <a class="cx-apps__bouton" href="{{ $lien }}" target="_blank" rel="noopener noreferrer">
                                        <span class="cx-apps__bouton-magasin">{{ $libelles[$destination]['magasin'] }}</span>
                                        <span class="cx-apps__bouton-action">{{ $libelles[$destination]['action'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        @if($carte['qr'] !== null)
                            <figure class="cx-apps__qr">
                                <div class="cx-apps__qr-cadre" role="img"
                                     aria-label="{{ __('vitrine.apps.qr_aide') }} — {{ $carte['titre'] }}">
                                    {!! $carte['qr'] !!}
                                </div>
                                <figcaption>{{ __('vitrine.apps.qr_aide') }}</figcaption>
                            </figure>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
