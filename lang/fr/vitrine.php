<?php

/*
 * Les textes de la vitrine : le film du parcours d'une mission, et l'invitation à télécharger
 * les applications. Ils passent par `__()` pour être modifiables depuis /admin/traductions
 * sans toucher au code — le centre i18n écrit dans `translation_overrides`.
 */

return [

    'accueil' => [
        'accroche' => 'Avec Brio, tout le monde gagne de l’argent.',
        'hero' => [
            'titre' => 'Avec Brio, tout le monde gagne de l’argent',
            'sous_titre' => 'Faites venir un pro chez vous en quelques questions. Et de l’autre côté, gagnez de l’argent : vos compétences, votre voiture, votre logement. Un seul compte pour les deux.',
            'puces' => [
                'Le prix s’affiche avant que vous donniez votre nom',
                'Votre voiture et votre logement peuvent vous rapporter',
                'Un pro garde 85 % de ce qu’il facture',
            ],
            'bouton' => 'Voir mon estimation',
            'bouton_secondaire' => 'Gagner de l’argent',
            'note' => 'Ouvert en Belgique, dans :zones régions, sur :metiers métiers.',
        ],
        'portes' => [
            'surtitre' => 'Vous êtes ici pour quoi ?',
            'titre' => 'Choisissez votre côté',
            'sous_titre' => 'Les deux vivent dans le même compte. Vous pouvez commander aujourd’hui et gagner de l’argent demain.',
            'client' => [
                'cle' => 'client',
                'titre' => 'J’ai besoin d’un pro',
                'phrase' => 'Un devis, un professionnel chez vous, et vous ne payez qu’une fois le travail fait.',
                'lien' => 'Voir ce que je peux commander',
            ],
            'gagnant' => [
                'cle' => 'gagnant',
                'titre' => 'Je veux gagner de l’argent',
                'phrase' => 'Vos compétences, votre voiture, votre logement, votre société : quatre façons d’encaisser sur Brio.',
                'lien' => 'Voir comment je gagne',
            ],
        ],
        'client' => [
            'surtitre' => 'Vous commandez',
            'titre' => 'Un pro chez vous, sans mauvaise surprise',
            'sous_titre' => 'Vous composez, vous voyez le prix, vous décidez. Le reste se passe tout seul.',
            'cartes' => [
                [
                    'titre' => 'Le prix avant votre nom',
                    'texte' => 'Choisissez un métier, répondez à ses questions : le montant bouge à chaque réponse. Sans compte, sans courriel, sans numéro de téléphone. C’est le bas de la fourchette qui vous engage, jamais le haut.',
                    'chiffre' => ':metiers métiers ouverts',
                    'image' => 'client-prix',
                ],
                [
                    'titre' => 'Vous ne payez qu’après',
                    'texte' => 'Le montant est bloqué sur votre carte quand un pro accepte, et prélevé seulement à la fin du travail. Au-delà de 500 €, vous choisissez : tout bloquer, ou 30 % à la commande et le solde à la fin.',
                    'chiffre' => 'Débité à la clôture, pas avant',
                    'image' => 'client-poignee',
                ],
                [
                    'titre' => 'Un code à six chiffres, chez vous',
                    'texte' => 'Rien ne démarre sans le code que vous montrez au professionnel. Sa position est vérifiée à l’arrivée, et chaque photo prise chez vous porte son heure, son lieu et sa signature numérique.',
                    'chiffre' => 'Valable 20 minutes',
                    'image' => '',
                ],
                [
                    'titre' => 'Une urgence ? Un pro part tout de suite',
                    'texte' => 'Sur :immediat métiers, votre demande part vers un professionnel en ligne près de chez vous, qui a 20 secondes pour répondre. La recherche s’élargit de 5 km en 5 km jusqu’à 20 km.',
                    'chiffre' => 'Plomberie, électricité, nettoyage, course',
                    'image' => '',
                ],
                [
                    'titre' => 'Plusieurs métiers, un seul chantier',
                    'texte' => 'Commandez la peinture, le carrelage et le nettoyage ensemble : les interventions s’enchaînent dans le bon ordre, temps de séchage compris, et la remise apparaît sur une ligne du devis.',
                    'chiffre' => '−5 % à deux métiers, −8 % à trois, −12 % à partir de quatre',
                    'image' => '',
                ],
                [
                    'titre' => 'Vous annulez quand vous voulez',
                    'texte' => 'Gratuit jusqu’à 48 heures avant. Ensuite le barème est écrit d’avance, et vous le lisez avant de réserver. Si c’est le pro qui ne vient pas, vous ne payez rien.',
                    'chiffre' => 'Gratuit au-delà de 48 heures',
                    'image' => '',
                ],
            ],
            'bouton' => 'Voir mon estimation',
        ],
        'gagnant' => [
            'surtitre' => 'Vous encaissez',
            'titre' => 'Quatre façons de gagner de l’argent',
            'sous_titre' => 'Vous n’avez pas besoin d’être artisan. Une voiture qui dort, une chambre libre, un savoir-faire : tout cela se loue sur Brio.',
            'cartes' => [
                [
                    'titre' => 'Votre métier',
                    'texte' => 'Vous recevez les missions de vos métiers et de vos zones, une offre à la fois. Vous refusez sans vous justifier. Brio prend 15 % de ce que le client règle — jamais en plus de son prix — et vous gardez le reste.',
                    'chiffre' => 'Vous gardez 85 %',
                    'lien' => 'Recevoir des missions',
                    'image' => 'gagne-artisan',
                ],
                [
                    'titre' => 'Votre voiture',
                    'texte' => 'Une voiture qui dort dans la rue ne rapporte rien. Mettez-la en location entre membres : vous fixez vos dates, vous validez chaque demande, et l’état des lieux se fait en photos horodatées, à la remise comme au retour.',
                    'chiffre' => 'Vous gardez 75 %',
                    'lien' => 'Mettre ma voiture en location',
                    'image' => 'gagne-voiture',
                ],
                [
                    'titre' => 'Votre logement',
                    'texte' => 'Une chambre libre, un studio vide, un appartement le temps d’un week-end : publiez-le et recevez des voyageurs. L’empreinte bancaire est prise avant l’arrivée, et un code à six chiffres ouvre le séjour.',
                    'chiffre' => 'Tout le logement, une chambre ou un lit',
                    'lien' => 'Mettre mon logement en location',
                    'image' => 'gagne-logement',
                ],
                [
                    'titre' => 'Votre société',
                    'texte' => 'Une société prestataire se pilote entièrement sur Brio : vos équipes, vos onze rôles, vos plannings, vos missions, vos factures et votre comptabilité. Vos employés travaillent depuis l’application terrain.',
                    'chiffre' => 'Onze rôles, une matrice de droits que vous réglez',
                    'lien' => 'Ouvrir un compte société',
                    'image' => 'gagne-societe',
                ],
            ],
        ],
        'confiance' => [
            'surtitre' => 'Des deux côtés',
            'titre' => 'Ce qui protège votre argent et votre porte',
            'points' => [
                [
                    'titre' => 'Le visage vérifié, au hasard',
                    'texte' => 'Sur les métiers les plus sensibles, la plateforme redemande un selfie et le compare à la pièce d’identité, à un moment tiré au sort. Qui échoue deux fois sort de la file des missions.',
                    'image' => 'confiance-facial',
                ],
                [
                    'titre' => 'La preuve reste',
                    'texte' => 'Chaque photo prise sur place est scellée : signature du fichier, heure, position. Seul le professionnel affecté peut en déposer une. En cas de litige, elle fait foi.',
                    'image' => '',
                ],
                [
                    'titre' => 'Un litige a un délai écrit',
                    'texte' => 'Vous ouvrez le dossier depuis votre compte. Première réponse sous 24 heures, 4 heures si c’est urgent, et le dossier monte tout seul d’un niveau s’il traîne. Dix issues sont possibles.',
                    'image' => '',
                ],
                [
                    'titre' => 'La conversation ne sort pas',
                    'texte' => 'Les numéros de téléphone et les adresses de courriel y sont masqués. L’échange reste dans la plateforme, et il reste disponible si ça tourne mal.',
                    'image' => '',
                ],
            ],
        ],
        'entreprises' => [
            'surtitre' => 'Pour les entreprises',
            'titre' => 'Vos locaux, vos équipes, vos factures',
            'sous_titre' => 'Une société se gère entièrement depuis son espace, des deux côtés du marché.',
            'cartes' => [
                [
                    'titre' => 'Chacun voit ce qu’il doit voir',
                    'texte' => 'Six rôles pour une entreprise cliente, onze pour une société prestataire, et les droits de chacun se règlent un à un.',
                ],
                [
                    'titre' => 'Un membre, ses locaux',
                    'texte' => 'Rattachez un membre à une liste d’adresses : il ne voit que celles-là, et ses factures suivent la même règle.',
                ],
                [
                    'titre' => 'Un budget par local',
                    'texte' => 'Posez un budget mensuel et le seuil qui déclenche l’alerte. Elle prévient sans bloquer la réservation.',
                ],
                [
                    'titre' => 'Vos réservations en un fichier',
                    'texte' => 'Déposez un fichier CSV et l’écran crée toutes les réservations d’un coup.',
                ],
                [
                    'titre' => 'Votre grille, sans code à saisir',
                    'texte' => 'Négociez une grille ligne à ligne ou une remise unique : le prix remisé sort tout seul quand vous réservez.',
                ],
                [
                    'titre' => 'Votre échéance de paiement',
                    'texte' => 'De 0 à 365 jours. Nous posons celle que vous avez négociée, et chaque facture l’affiche.',
                ],
                [
                    'titre' => 'Vos écritures comptables',
                    'texte' => 'Téléchargez-les en tableur ou au format FEC, celui que votre expert-comptable sait ouvrir.',
                ],
                [
                    'titre' => 'Vos équipes sur le terrain',
                    'texte' => 'Une société prestataire affecte ses employés, suit leurs missions et leurs plannings depuis le même espace.',
                ],
            ],
            'bouton' => 'Ouvrir un compte entreprise',
        ],
        'questions' => [
            'titre' => 'Quatre questions qu’on nous pose',
            'items' => [
                [
                    'q' => 'Combien ça me coûte de m’inscrire ?',
                    'r' => 'Rien. Pas d’abonnement, pas d’achat de contacts. Brio prend sa commission sur une mission terminée : 15 % sur une prestation, 25 % sur une location. Tant que vous n’avez rien gagné, vous ne devez rien.',
                ],
                [
                    'q' => 'Quand suis-je payé ?',
                    'r' => 'Votre part part sur votre compte dès la clôture. Stripe la vire ensuite vers votre banque selon son calendrier, en général sous sept jours.',
                ],
                [
                    'q' => 'Et si le travail est mal fait ?',
                    'r' => 'Vous ouvrez un litige depuis votre compte. Les photos horodatées et votre conversation servent de preuve, et le versement du professionnel est gelé sur cette intervention tant que votre réclamation court.',
                ],
                [
                    'q' => 'Je peux commander ET gagner de l’argent ?',
                    'r' => 'Oui, avec le même compte. Vous faites venir un peintre le lundi et vous louez votre voiture le samedi. Rien à recréer, rien à rebasculer.',
                ],
            ],
        ],
        'final' => [
            'titre' => 'Commencez du côté que vous voulez',
            'sous_titre' => 'Le parcours de commande s’ouvre sans compte. L’inscription est gratuite et ne vous engage à rien.',
            'bouton' => 'Voir mon estimation',
            'bouton_secondaire' => 'Gagner de l’argent',
        ],
    ],

    'film' => [
        'eyebrow' => 'De la réservation à la poignée de main',
        'titre' => 'Une mission, du globe à votre porte.',
        'sous_titre' => 'Faites défiler. Tout ce que fait Brio tient en quatorze plans.',
        'progression' => 'Étape :courante sur :total',

        'plans' => [
            1 => [
                'titre' => 'Où que vous soyez',
                'detail' => ':metiers métiers ouverts, dans :zones régions de Belgique.',
            ],
            2 => [
                'titre' => 'Une exigence, partout la même',
                'detail' => 'De Bruxelles à Gand, le même déroulé et les mêmes règles.',
            ],
            3 => [
                'titre' => 'Un maillage réel',
                'detail' => 'Des pros qui ont choisi votre métier et votre zone à l’inscription.',
            ],
            4 => [
                'titre' => 'Ce soir, chez vous',
                'detail' => 'Une fuite ne prend pas rendez-vous. Brio non plus.',
            ],
            5 => [
                'titre' => 'Le métier, l’adresse, l’heure',
                'detail' => 'Trois réponses, et vous avez un prix. Pas de compte à créer.',
            ],
            6 => [
                'titre' => 'Le prix avant l’identité',
                'detail' => 'Vous voyez une fourchette de prix avant de donner votre nom.',
            ],
            7 => [
                'titre' => 'Un pro vérifié accepte',
                'detail' => 'Il choisit sa mission. Vous voyez son nom et sa note avant qu’il sonne.',
            ],
            8 => [
                'titre' => 'Suivez-le en temps réel',
                'detail' => 'Sa position et son heure d’arrivée, en direct sur la carte.',
            ],
            9 => [
                'titre' => 'Il scanne, la mission démarre',
                'detail' => 'Le code à six chiffres, c’est vous qui le donnez. Sans lui, rien ne démarre.',
            ],
            10 => [
                'titre' => 'Le travail est fait',
                'detail' => 'Par quelqu’un du métier, avec ses outils et son expérience.',
            ],
            11 => [
                'titre' => 'Il rescanne, la mission se ferme',
                'detail' => 'Photos horodatées et empreintées : la preuve reste, même en cas de litige.',
            ],
            12 => [
                'titre' => 'Le paiement part, enfin',
                'detail' => 'Rien n’était prélevé jusqu’ici. La carte n’est débitée qu’à la clôture.',
            ],
            13 => [
                'titre' => 'Tout le monde repart gagnant',
                'detail' => 'Un travail fait, un pro payé, une confiance méritée.',
            ],
            14 => [
                'titre' => 'Et ça recommence',
                'detail' => 'Même déroulé la prochaine fois, et le même code à six chiffres.',
            ],
        ],
    ],

    'apps' => [
        'eyebrow' => 'Emportez Brio',
        'titre' => 'L’application qui va avec.',
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
            'ios' => 'Télécharger sur l’App Store',
            'android' => 'Disponible sur Google Play',
            'smartlink' => 'Télécharger l’application',
        ],

        'qr_aide' => 'Scannez ce code avec votre téléphone',
    ],

];
