<?php

/*
 * Les textes de la vitrine : le film du parcours d'une mission, et l'invitation à télécharger
 * les applications. Ils passent par `__()` pour être modifiables depuis /admin/traductions
 * sans toucher au code — le centre i18n écrit dans `translation_overrides`.
 */

return [

    'film' => [
        'eyebrow' => 'De la réservation à la poignée de main',
        'titre' => 'Une mission, du globe à votre porte.',
        'sous_titre' => 'Faites défiler. Tout ce que fait Brio tient en quatorze plans.',
        'progression' => 'Étape :courante sur :total',

        'plans' => [
            1 => [
                'titre' => 'Où que vous soyez',
                'detail' => 'Plus de 30 métiers couverts, dans 9 pays.',
            ],
            2 => [
                'titre' => 'Une plateforme, un continent',
                'detail' => 'La même exigence à Bruxelles qu\'à Casablanca.',
            ],
            3 => [
                'titre' => 'Un maillage réel',
                'detail' => 'Des professionnels vérifiés, à quelques rues de chez vous.',
            ],
            4 => [
                'titre' => 'Ce soir, chez vous',
                'detail' => 'Une fuite ne prend pas rendez-vous. Brio non plus.',
            ],
            5 => [
                'titre' => 'Réservez en 30 secondes',
                'detail' => 'Un métier, une adresse, un créneau. C\'est parti.',
            ],
            6 => [
                'titre' => 'Le prix avant l\'identité',
                'detail' => 'Une photo suffit : fourchette estimée en 10 secondes, sans appel commercial.',
            ],
            7 => [
                'titre' => 'Un pro vérifié accepte',
                'detail' => 'Identité contrôlée, assurance vérifiée, note publique.',
            ],
            8 => [
                'titre' => 'Suivez-le en temps réel',
                'detail' => 'Sa position et son heure d\'arrivée, en direct sur la carte.',
            ],
            9 => [
                'titre' => 'Il scanne, la mission démarre',
                'detail' => 'Horodatée sur place : sa présence est prouvée, pas déclarée.',
            ],
            10 => [
                'titre' => 'Le travail est fait',
                'detail' => 'Par un homme du métier, avec ses outils et son expérience.',
            ],
            11 => [
                'titre' => 'Il rescanne, la mission se ferme',
                'detail' => 'Validée des deux côtés, photos et horodatage à l\'appui.',
            ],
            12 => [
                'titre' => 'Le paiement se libère',
                'detail' => 'Votre argent était séquestré jusqu\'ici. Vous notez, il est payé.',
            ],
            13 => [
                'titre' => 'Tout le monde repart gagnant',
                'detail' => 'Un travail fait, un pro payé, une confiance méritée.',
            ],
            14 => [
                'titre' => 'Et ça recommence, partout',
                'detail' => 'Des milliers de missions comme celle-là, chaque jour.',
            ],
        ],
    ],

    'apps' => [
        'eyebrow' => 'Emportez Brio',
        'titre' => 'L\'application qui va avec.',
        'sous_titre' => 'Deux applications, deux métiers. Prenez la vôtre.',

        'client' => [
            'titre' => 'Brio — pour réserver',
            'accroche' => 'Réservez, suivez le prestataire, validez, payez. Depuis votre poche.',
        ],

        'prestataire' => [
            'titre' => 'Brio Provider — pour travailler',
            'accroche' => 'Recevez les missions près de vous, scannez sur place, encaissez.',
        ],

        'liens' => [
            'ios' => 'Télécharger sur l\'App Store',
            'android' => 'Disponible sur Google Play',
            'smartlink' => 'Télécharger l\'application',
        ],

        'qr_aide' => 'Scannez ce code avec votre téléphone',
    ],

];
