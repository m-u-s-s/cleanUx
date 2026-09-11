<?php

/*
 * Les textes de la vitrine : le film du parcours d'une mission, et l'invitation à télécharger
 * les applications. Ils passent par `__()` pour être modifiables depuis /admin/traductions
 * sans toucher au code — le centre i18n écrit dans `translation_overrides`.
 */

return [

    'accueil' => [
        'position' => 'Chaque chiffre de cette page porte sa condition, et ce que nous ne savons pas encore faire, nous l’écrivons aussi.',
        'hero' => [
            'titre' => 'Vous voulez repeindre le salon, sans savoir combien',
            'sous_titre' => 'Vous voyez votre estimation, ligne par ligne, avant de donner votre nom. Le montant est bloqué sur votre carte, et prélevé une fois le travail fait.',
            'puces' => [
                'Vous voyez votre estimation se préciser à chaque réponse',
                'Vous montrez un code à six chiffres au professionnel, et le travail commence',
                'Vous annulez sans frais tant qu’il reste plus de 48 h avant le rendez-vous',
            ],
            'bouton' => 'Voir mon estimation',
            'notes' => [
                'Aujourd’hui en Belgique. Votre adresse est vérifiée avant que vous confirmiez : si nous n’intervenons pas encore chez vous, c’est dit là.',
                'Au-delà de 500 €, une seconde formule vous est proposée : 30 % à la commande, le solde bloqué jusqu’à la fin du travail. Vous choisissez laquelle des deux.',
            ],
        ],
        'situations' => [
            'surtitre' => 'Quatre situations qui reviennent',
            'titre' => 'Dites ce qui se passe chez vous',
            'sous_titre' => 'Les trois premières situations suivent le même parcours, qui pose les questions du métier que vous choisissez. La quatrième commence par l’ouverture d’un compte d’entreprise.',
            'cartes' => [
                [
                    'titre' => 'C’est urgent aujourd’hui',
                    'textes' => [
                        ':immediat métiers sur :metiers acceptent l’intervention immédiate : la plomberie, l’électricité, le nettoyage à domicile et le transport d’un point à un autre.',
                        'Votre zone doit l’ouvrir aussi : sinon, vous ne pouvez pas commander en immédiat.',
                    ],
                    'puces' => [
                        'L’intervention immédiate coûte 30 % de plus, et sa fourchette s’élargit de 15 % parce que le questionnaire est plus court. Vous voyez les deux avant de confirmer.',
                        'La recherche part de 5 km autour de votre adresse et s’élargit de 5 km en 5 km, jusqu’à 20 km. L’offre part vers un professionnel à la fois — et, au dernier rayon seulement, vers vingt d’un coup, au premier qui accepte.',
                        'Tant que personne n’a accepté, vous arrêtez la recherche sans rien payer. Une fois un professionnel lancé vers vous, vous gardez 3 minutes pour l’arrêter sans frais ; après, l’arrêt vous coûte 5 €.',
                    ],
                    'bouton' => 'Voir mon estimation',
                ],
                [
                    'titre' => 'C’est la même chose toutes les semaines',
                    'textes' => [
                        'Vous refaites la commande dans le même parcours, et le devis garde chaque question mot pour mot, telle que vous l’avez lue.',
                        'Un professionnel déjà venu chez vous compte pour 10 % dans le classement de ceux à qui l’offre est envoyée.',
                    ],
                    'puces' => [
                        'Vous fixez le jour et l’heure, et le professionnel a 30 minutes pour accepter.',
                        'S’il ne la prend pas, votre demande passe au professionnel suivant, et ainsi de suite jusqu’à cinq fois.',
                        'Votre carte n’est retenue qu’une fois que le professionnel a accepté, et débitée quand le travail est terminé.',
                    ],
                    'bouton' => 'Voir mon estimation',
                ],
                [
                    'titre' => 'C’est un chantier à plusieurs métiers',
                    'textes' => [
                        'Vous commandez la peinture et le nettoyage de fin de chantier en une seule commande, et les interventions se suivent dans le bon ordre.',
                        'La remise apparaît sur une ligne de votre devis, et non cachée dans le total.',
                    ],
                    'puces' => [
                        'En commande groupée : 5 % de remise pour deux métiers, 8 % pour trois, 12 % à partir de quatre',
                        'Après la peinture, le nettoyage de fin de chantier attend que ça sèche : ce délai est porté par le chantier, pas deviné.',
                        'Chaque métier reste une intervention à part, avec son professionnel et son paiement.',
                    ],
                    'bouton' => 'Voir mon estimation',
                ],
                [
                    'titre' => 'C’est pour mon entreprise, sur plusieurs locaux',
                    'textes' => [
                        'Vous rattachez vos locaux à un compte d’entreprise, et une seule saisie lance la même intervention sur plusieurs locaux.',
                        'Vous décidez ce que chaque membre voit : six rôles sont prévus pour une entreprise cliente, et vous ajustez les permissions de chacun.',
                    ],
                    'puces' => [
                        'Chaque local a son budget mensuel, avec un seuil d’alerte que vous réglez. L’alerte prévient, elle ne bloque pas la commande.',
                        'Vous pouvez restreindre un membre à une liste de locaux : il ne voit alors que ces adresses, et ses factures suivent la même règle. Tant que vous ne posez pas cette restriction, il voit tous vos locaux.',
                        'Votre comptable télécharge votre comptabilité, en tableur ou au format légal des écritures comptables.',
                    ],
                    'bouton' => 'Ouvrir un compte d’entreprise',
                ],
            ],
            'note' => 'Brio s’ouvre pays par pays et zone par zone — aujourd’hui, la Belgique : vous ne pouvez commander un métier chez vous que si son prix y est déjà fixé.',
        ],
        'fonctionnement' => [
            'surtitre' => 'Ce qui se passe, dans l’ordre',
            'titre' => 'Du prix affiché au code que vous montrez',
            'temps' => [
                [
                    'titre' => 'Le prix arrive avant votre nom',
                    'textes' => [
                        'Vous choisissez un secteur, puis un métier, puis vous répondez à ses questions ; sur les métiers déjà questionnés, chaque réponse — la surface, le nombre de pièces, le créneau — fait bouger le montant sous vos yeux. La toiture et l’élagage, eux, se chiffrent sur devis et n’affichent aucun montant.',
                        'Sans compte, sans courriel et sans numéro de téléphone : là où un montant s’affiche, vous savez entre quels montants se situe le prix avant d’avoir donné votre nom.',
                    ],
                ],
                [
                    'titre' => 'Un professionnel de votre zone accepte',
                    'textes' => [
                        'Si nous n’intervenons pas encore à votre adresse, la confirmation vous le dit en toutes lettres, avant tout engagement.',
                        'Pour les :immediat métiers ouverts à l’intervention immédiate, et dans les zones qui l’ouvrent, votre demande part chez un seul professionnel, qui a 20 à 30 secondes pour répondre. Si personne ne prend, le rayon s’élargit de 5 km en 5 km jusqu’à 20 km, et au dernier rayon l’offre part vers vingt professionnels à la fois : le premier qui accepte l’emporte.',
                        'Pour un rendez-vous planifié — le seul regime ouvert aux autres metiers — le professionnel décide en 30 minutes, et votre demande passe au suivant s’il ne répond pas, jusqu’à cinq fois.',
                    ],
                ],
                [
                    'titre' => 'Le code, c’est vous qui l’avez',
                    'textes' => [
                        'Chez vous, le professionnel arrive, saisit le code à six chiffres que vous lui montrez, et le travail commence. Sur un trajet, c’est votre montée à bord qui lance la course.',
                        'Une fois le professionnel trouvé, et seulement à ce moment-là, le montant est bloqué sur votre carte : il y reste pendant le travail, et vous n’êtes débité qu’à la fin. Au-delà de 500 €, une seconde formule propose un acompte de 30 % débité le jour même et le solde bloqué jusqu’à la fin — les deux montants sont affichés avant le clic.',
                    ],
                ],
            ],
        ],
        'confiance' => [
            'surtitre' => 'Ce qui se passe à votre porte',
            'titre' => 'Qui entre chez vous, et ce qu’il en reste',
            'points' => [
                [
                    'titre' => 'Le code à six chiffres est chez vous',
                    'textes' => [
                        'Vous le montrez au professionnel, qui le saisit pour démarrer le travail. Il vous parvient à son arrivée et reste valable 20 minutes.',
                        'Une intervention chez vous ne démarre qu’avec le code que vous montrez. Sur un trajet, c’est votre montée à bord qui lance la course.',
                    ],
                ],
                [
                    'titre' => 'Chaque geste porte son verdict de position',
                    'textes' => [
                        'La plateforme confronte le relevé du téléphone au lieu de l’intervention, écarte toute position simulée et tient le professionnel pour présent à moins de 250 m — une tolérance élargie quand le téléphone annonce lui-même un relevé imprécis, et un geste estampillé « sans position » quand il n’y en a aucune.',
                    ],
                ],
                [
                    'titre' => 'Une photo prise chez vous est scellée',
                    'textes' => [
                        'Seul le professionnel affecté à votre intervention peut y déposer une photo, et chacune est scellée à la prise : signature du fichier, heure, et position quand l’appareil en donne une.',
                    ],
                ],
                [
                    'titre' => 'La conversation reste dans la plateforme',
                    'textes' => [
                        'Votre conversation s’ouvre dès la réservation. La plateforme y masque les numéros de téléphone et les adresses de courriel, et l’échange reste disponible en cas de litige.',
                    ],
                ],
            ],
        ],
        'prix' => [
            'surtitre' => 'Le prix, en entier',
            'titre' => 'Vous voyez votre estimation avant votre nom',
            'sous_titre' => 'Vous composez votre commande, le montant bouge à chaque réponse, et personne ne vous demande qui vous êtes pour l’afficher.',
            'paragraphes' => [
                'Vous choisissez un secteur, puis un métier, puis vous répondez à ses questions. À chaque réponse, le montant se recalcule et le détail s’allonge d’une ligne — quand ce métier est déjà questionné. Vous lisez ce montant sans créer de compte, sans laisser de courriel ni de numéro de téléphone. Le jour où vous réservez, le devis se fige, et les questions y restent écrites mot pour mot, telles que vous les avez lues.',
                'Tant qu’une réponse manque, vous voyez deux montants au lieu d’un : le plus bas et le plus haut que vos réponses permettent. Ce qui vous engage, c’est le bas de la fourchette, jamais le haut. L’écart s’ajuste à la fin, sur ce qui a réellement été fait.',
                'Brio se paie sur le montant de l’intervention, jamais en plus. Sa part est prise au moment du paiement, et votre total ne bouge pas. Le pourboire, lui, n’est pas compris : vous le décidez après l’intervention, pendant 7 jours, et nous n’en retenons rien aujourd’hui.',
                'Deux métiers n’affichent aucun chiffre : la toiture et l’élagage passent par un devis, et le disent d’entrée. :sansQuestionnaire autres attendent encore leur questionnaire : tant qu’il n’est pas écrit, le parcours ne sait pas les chiffrer, et le montant qu’il affiche pour eux ne veut rien dire — pour ceux-là, demandez-nous un devis.',
            ],
            'variations' => [
                'titre' => 'Ce qui fait varier le montant',
                'puces' => [
                    'Vos réponses comptent d’abord : une surface mesurée, une quantité, une option cochée ajoutent chacune leur ligne au devis.',
                    'Votre zone compte ensuite : chaque métier a son tarif zone par zone, et il existe des zones où un métier n’est pas ouvert du tout. Vous composez d’abord ; c’est à la confirmation, votre adresse saisie, que l’on vous dit si l’on intervient chez vous — et il arrive que la réponse soit non.',
                    'L’urgence coûte : une intervention immédiate ajoute 30 % au total, et la fourchette s’élargit de 15 %, parce que le questionnaire est plus court.',
                    ':immediat métiers sur :metiers acceptent l’intervention immédiate, et seulement dans les zones qui l’ouvrent.',
                    'Commandés ensemble dans le mode « chantier », plusieurs métiers font baisser le total : 5 % pour deux métiers, 8 % pour trois, 12 % à partir de quatre. Commandés séparément, aucune remise ne s’applique. Elle apparaît sur une ligne du devis, et non cachée dans le total.',
                    'Un trajet se calcule sur la distance : au tarif de référence, 2,50 € à la prise en charge, 1,40 € par kilomètre au-delà du premier et 0,30 € par minute. Ce barème se règle zone par zone, et votre zone peut le majorer — le montant que vous lisez à l’écran est celui de votre zone.',
                ],
            ],
            'note_acompte' => 'À partir de 500 €, une seconde formule apparaît : vous réglez 30 % à la commande, et le solde reste bloqué jusqu’à la fin de l’intervention. Les deux montants s’affichent avant le clic. Un métier qui passe par un devis n’y a pas droit.',
            'annulation' => [
                'titre' => 'Si vous annulez',
                'puces' => [
                    'Vous annulez plus de 48 h avant l’heure prévue : vous ne payez rien.',
                    'Entre 48 h et 24 h avant, vous réglez 25 % du montant.',
                    'Entre 24 h et 2 h avant, vous réglez la moitié du montant.',
                    'À moins de 2 h, vous réglez la totalité du montant.',
                    'Si le professionnel est déjà en route, vos frais ne descendent jamais sous 5 % du montant : ces 5 % ne s’ajoutent pas au barème, ils en relèvent le plancher.',
                    'Une force majeure vous dispense de ces frais, sur justificatif ; une urgence médicale aussi, sur justificatif et dans la limite de deux fois par période de 30 jours. Et si c’est le professionnel qui ne vient pas, vous ne payez rien, sans rien avoir à prouver.',
                    'Tant que personne n’a accepté votre recherche immédiate, vous l’annulez gratuitement, sans limite de temps. Une fois un professionnel lancé vers vous, vous gardez 3 minutes pour annuler sans frais ; ensuite, l’annulation coûte 5 €, et ce prix est annoncé avant le clic.',
                ],
            ],
            'aveu' => [
                'titre' => 'Brio n’est pas le moins cher',
                'paragraphes' => [
                    'Brio n’est pas le moins cher, et le motif tient en trois choses que vous payez sans les voir. D’abord, votre argent n’est pas encaissé d’avance : une fois qu’un professionnel a accepté, le montant est bloqué sur votre carte, et vous n’êtes débité qu’à la fin de l’intervention — seul l’acompte, si vous choisissez cette formule, part le jour même.',
                    'Ensuite, chaque geste sur place laisse une trace : les photos portent leur signature numérique, leur heure et leur position, et vos messages restent dans la plateforme.',
                    'Enfin, si l’intervention se passe mal, vous ouvrez un litige, et le délai de réponse est écrit d’avance. Un litige urgent vise une première réponse en 4 h, un litige ordinaire en 24 h. Et ce n’est pas une intention en l’air : quand le délai passe, le dossier monte d’un niveau tout seul, une première fois après 24 h, une deuxième fois après 48 h. Tout cela se paie, et c’est dans le prix.',
                ],
            ],
        ],
        'engagements' => [
            'surtitre' => 'Les engagements',
            'titre' => 'Ce que nous nous engageons à tenir',
            'sous_titre' => 'Chaque ligne décrit une règle que la plateforme applique, avec son chiffre et le moment où elle s’applique. Aucune n’est une promesse commerciale : ce sont des réglages, et ils sont écrits ici tels qu’ils tournent.',
            'puces' => [
                'Le parcours de commande s’ouvre sans compte : vous composez et vous voyez le montant avant qu’on vous demande qui vous êtes. Deux métiers se font sur devis, la toiture et l’élagage, et le disent d’entrée. Partout où un montant s’affiche, c’est le bas de la fourchette qui est retenu, jamais le haut.',
                'Votre carte n’est touchée qu’une fois votre professionnel trouvé. Le montant y est alors bloqué, et nous ne le prélevons qu’à la fin de l’intervention.',
                'Sur un rendez-vous planifié, vous annulez sans frais tant qu’il reste plus de 48 h avant l’heure convenue. Si le professionnel est déjà en route, vos frais ne descendent jamais sous 5 % du montant.',
                'Une recherche immédiate s’annule gratuitement tant que personne n’a accepté. Une fois un professionnel lancé vers vous, vous gardez 3 minutes sans frais ; ensuite, l’annulation coûte 5 €.',
                'Vous ouvrez un litige depuis votre compte. Un délai de première réponse s’inscrit dessus — 24 h en priorité normale, 4 h si la situation est urgente — et un dossier qui dépasse ce délai monte d’un niveau tout seul, une première fois après 24 h, une deuxième fois après 48 h.',
            ],
            'chiffre' => 'Ensuite : 25 % entre 48 h et 24 h, 50 % entre 24 h et 2 h, 100 % dans les deux dernières heures',
            'note' => 'Le montant affiché reste une estimation : la fourchette se resserre à chaque réponse que vous donnez, et l’écart s’ajuste à la fin, sur ce qui a réellement été fait.',
            'bouton' => 'Voir mon estimation',
        ],
        'habitude' => [
            'surtitre' => 'Vous avez déjà un artisan',
            'titre' => 'Vous avez déjà quelqu’un ? Tant mieux.',
            'sous_titre' => 'Gardez votre plombier : il connaît votre installation. Ouvrez Brio pour les quatre moments où il ne peut pas répondre.',
            'intro' => 'Votre artisan connaît votre maison, et il vous connaît. Vous gardez son numéro ; vous ouvrez Brio les jours où il ne peut pas venir.',
            'moments' => [
                [
                    'titre' => 'Un dimanche',
                    'textes' => [
                        'Vous lancez une recherche immédiate : votre demande part vers un seul professionnel en ligne, et aucun professionnel ne reçoit deux offres en même temps. La recherche s’élargit de 5 km à chaque vague, sans dépasser 20 km.',
                    ],
                    'notes' => [
                        'Le professionnel a 20 s pour accepter, 30 s en plomberie et en électricité',
                        'La recherche immédiate ne concerne que :immediat métiers — plomberie, électricité, nettoyage à domicile, transport d’un point à un autre — et seulement dans les zones où ces métiers sont ouverts.',
                        '« En ligne » veut dire : le professionnel est connecté, sa position est connue, et son signal date de moins de 5 minutes.',
                    ],
                ],
                [
                    'titre' => 'La semaine du 15 août',
                    'textes' => [
                        'Votre demande de rendez-vous part vers un seul professionnel, qui a 30 minutes pour répondre ; passé ce délai, elle part vers le suivant, jusqu’à cinq fois. Au-delà, nous attribuons l’intervention au meilleur candidat qui ne l’a pas refusée.',
                    ],
                    'notes' => [
                        'Si nous ne servons pas encore votre adresse, vous le lisez à la confirmation, avec le motif écrit en toutes lettres.',
                    ],
                ],
                [
                    'titre' => 'Un chantier trop gros pour une personne',
                    'textes' => [
                        'Vous commandez trois métiers en une seule fois : les interventions se suivent dans le bon ordre, et quand deux métiers ont une dépendance connue — la peinture après l’électricité, le nettoyage après la peinture — le temps de séchage garde sa place entre deux interventions.',
                    ],
                    'notes' => [
                        'Chaque métier reste une intervention à part, avec son professionnel, son code de démarrage et son paiement.',
                        'En chantier groupé : 5 % de remise pour deux métiers, 8 % pour trois, 12 % à partir de quatre — une ligne à part sur le devis',
                    ],
                ],
                [
                    'titre' => 'Un justificatif qu’on vous réclame après coup',
                    'textes' => [
                        'Le devis est figé au moment de la réservation : les questions y restent écrites mot pour mot, telles que vous les avez lues.',
                    ],
                    'notes' => [
                        'Les photos prises sur place portent leur heure, leur position et une signature numérique. Quand un rapport de fin est signé devant vous, la signature est horodatée et le texte signé conservé. Vos échanges sont archivés à la fin de l’intervention.',
                    ],
                ],
            ],
            'bouton' => 'Voir mon estimation',
        ],
        'prestataire' => [
            'charniere' => 'Sur une intervention, Brio ne se paie qu’à la fin : nous prenons notre commission sur le montant que le client règle, une fois le travail terminé.',
            'surtitre' => 'Vous êtes couvreur, peintre ou plombier',
            'titre' => 'Une offre à la fois, et un refus qui compte',
            'cartes' => [
                [
                    'titre' => 'Ce que vous gardez',
                    'textes' => [
                        'Brio prélève 15 % du montant de l’intervention. Jamais moins de 2 € par prestation. Nous la déduisons de ce que le client règle : elle ne s’ajoute jamais au prix qu’il voit.',
                    ],
                ],
                [
                    'titre' => 'Comment une mission vous arrive',
                    'textes' => [
                        'Un rendez-vous planifié vous est proposé quand il tombe dans une zone que vous avez déclarée. Une intervention immédiate vous est proposée quand elle se déclenche près de votre position — 5 km d’abord, élargi de 5 km en 5 km jusqu’à 20 km. Dans les deux cas, vous êtes seul à recevoir l’offre : personne ne reçoit deux offres à la fois.',
                        'Sur un rendez-vous planifié — le seul régime ouvert à la couverture et à la peinture — vous avez 30 minutes pour répondre. L’intervention immédiate ne concerne que :immediat métiers : plomberie, électricité, nettoyage et transport d’un point à un autre, et seulement là où la zone l’autorise. La réponse s’y joue en 20 secondes, 30 en plomberie et en électricité.',
                    ],
                ],
                [
                    'titre' => 'Vous décidez, à chaque offre',
                    'textes' => [
                        'Vous refusez une offre sans donner de motif, et nous la proposons au professionnel suivant : un refus exprimé vous retire définitivement de cette mission. Laisser l’offre expirer ne vous en retire pas — au bout de cinq tentatives sans preneur, un rendez-vous planifié est attribué d’office au meilleur candidat qui ne l’a pas refusé.',
                        'Vous déclarez vos métiers et vos zones à l’inscription, et la liste ne vous montre que les métiers déjà ouverts sur la plateforme.',
                    ],
                ],
                [
                    'titre' => 'Ce qui vous fait monter dans le classement',
                    'textes' => [
                        'Sur un rendez-vous planifié, c’est votre note qui pèse le plus lourd : elle compte pour 25 % d’un classement à neuf mesures, devant votre taux d’acceptation et votre proximité, à 15 % chacun. Sur une intervention immédiate, c’est la distance qui passe devant, et le classement ne départage que deux professionnels aussi proches l’un que l’autre.',
                        'Les badges affichés sur votre profil, eux, n’entrent dans aucune des neuf mesures.',
                    ],
                ],
                [
                    'titre' => 'Ce que ça coûte avant la première mission',
                    'textes' => [
                        'Vous ne payez pas d’abonnement et vous n’achetez pas de contacts : au réglage livré, le seul prélèvement de Brio est sa commission, sur une mission terminée. Vous ne nous devez rien tant que vous n’avez pas travaillé.',
                        'Votre inscription tient en six étapes. Les pièces demandées dépendent des métiers que vous déclarez, et votre compte s’ouvre sans intervention humaine dès qu’aucune pièce bloquante ne manque. Les offres, elles, commencent à vous parvenir une fois vos pièces examinées : nous ne vous promettons aucun délai.',
                    ],
                ],
            ],
            'bouton' => 'Recevoir des missions',
        ],
        'entreprises' => [
            'surtitre' => 'Pour les entreprises',
            'titre' => 'Une entreprise commande, chaque local reste séparé',
            'sous_titre' => 'Votre société ouvre un espace où vous pouvez restreindre un membre à une liste de locaux : il ne voit alors que ces adresses, et ses factures suivent la même règle. Tant que vous ne posez pas cette restriction, le membre voit tous vos locaux.',
            'cartes' => [
                [
                    'titre' => 'Vous décidez qui fait quoi',
                    'textes' => [
                        'Vous donnez à chaque membre l’un des six rôles prévus pour une entreprise cliente, et vous ajustez ses permissions une à une depuis l’écran des membres. Une société prestataire, elle, dispose de onze rôles et d’un écran qui réécrit la table entière, rôle par rôle.',
                    ],
                ],
                [
                    'titre' => 'Un membre, ses locaux, rien d’autre',
                    'textes' => [
                        'Vous rattachez un membre à une liste de locaux. Il ne voit que ces adresses, et ses factures suivent la même règle.',
                    ],
                ],
                [
                    'titre' => 'Un budget par local, et son alerte',
                    'textes' => [
                        'Vous posez un budget mensuel sur chaque local et le seuil qui déclenche l’alerte. Ce budget vous prévient, il n’arrête pas la réservation.',
                    ],
                ],
                [
                    'titre' => 'Vos réservations arrivent dans un seul fichier',
                    'textes' => [
                        'Depuis votre espace, vous déposez vos réservations dans un fichier CSV de 2 Mo au maximum. L’écran les crée d’un coup.',
                    ],
                ],
                [
                    'titre' => 'Votre grille s’applique sans code à saisir',
                    'textes' => [
                        'Vous négociez une grille ligne à ligne, ou une remise unique de 0 à 100 %. Nous l’écrivons dans votre contrat-cadre, qui désigne la société chargée de vos interventions. Le prix remisé sort tout seul quand vous réservez.',
                    ],
                ],
                [
                    'titre' => 'Vous payez dans le délai négocié',
                    'textes' => [
                        'Votre échéance de paiement va de 0 à 365 jours. Nous posons celle que vous avez négociée, et chaque facture que nous vous éditons l’affiche.',
                    ],
                ],
                [
                    'titre' => 'Votre comptable a vos écritures',
                    'textes' => [
                        'Vous téléchargez vos écritures depuis votre espace, en tableur ou au format FEC, le fichier des écritures que votre expert-comptable sait ouvrir. Nous refusons une écriture déséquilibrée, comme une écriture qui tombe dans une période close.',
                    ],
                ],
                [
                    'titre' => 'Nous comptons la ponctualité sans la maquiller',
                    'textes' => [
                        'Vous lisez le taux de ponctualité de vos interventions. Une intervention sans relevé d’arrivée compte à part : nous ne la rangeons jamais parmi celles arrivées à l’heure.',
                    ],
                ],
            ],
            'note' => 'Votre contrat, votre grille et votre remise s’écrivent chez nous : vous les lisez dans votre espace, et nous les modifions à votre demande.',
            'bouton' => 'Ouvrir un compte d’entreprise',
        ],
        'questions' => [
            'titre' => 'Six questions gênantes, six réponses',
            'sous_titre' => 'Vous cherchez ce que nous évitons de dire. Le voici, avec le délai, le périmètre et le chemin pour nous joindre.',
            'items' => [
                [
                    'q' => 'Et si le travail est mal fait ?',
                    'r' => 'Vous ouvrez un litige depuis votre compte, en le rattachant à la réservation concernée. Chaque dossier porte une échéance de première réponse : 24 heures en priorité normale, 4 heures sur un dossier urgent. Passé cette échéance, le dossier monte d’un niveau au bout de 24 heures, puis d’un niveau de plus après 48 heures — l’escalade, elle, est automatique. Les photos horodatées de l’intervention et votre conversation restent dans la plateforme et servent de preuve. Dix issues sont possibles, et nous choisissons la vôtre au cas par cas.',
                ],
                [
                    'q' => 'Et si quelque chose casse chez moi ?',
                    'r' => 'Nous ne vous vendons aucune assurance et nous ne couvrons aucun dégât : ce serait faux de l’écrire. Votre professionnel dépose une attestation de responsabilité civile professionnelle quand son métier l’exige, et son compte ne s’ouvre pas tant que la pièce manque. Chaque photo prise chez vous porte sa signature, son heure et sa position. Vous ouvrez un litige, et nous gelons le versement du professionnel sur cette intervention tant que votre réclamation court.',
                ],
                [
                    'q' => 'Combien je perds si j’annule ?',
                    'r' => 'Plus de 48 heures avant l’intervention, vous ne payez rien. Entre 48 et 24 heures, vous payez 25 % du montant. Entre 24 et 2 heures, vous en payez la moitié. À moins de 2 heures, vous payez la totalité. Si le professionnel est déjà en route, vos frais ne descendent jamais sous 5 % du montant : ces 5 % ne s’ajoutent pas au barème, ils en relèvent le plancher. Nous ne prélevons que ces frais sur votre empreinte bancaire, et nous libérons le reste. Une force majeure vous en dispense, sur justificatif ; une urgence médicale aussi, sur justificatif et dans la limite de deux fois par période de 30 jours.',
                ],
                [
                    'q' => 'Qui sont vos professionnels exactement ?',
                    'r' => 'Un professionnel qui s’inscrit sur la plateforme franchit six étapes obligatoires avant de recevoir la moindre mission, et son compte ne s’ouvre pas tant qu’il manque une pièce. Les pièces demandées dépendent des métiers qu’il déclare : une certification sur un métier réglementé, un permis et une assurance de véhicule sur un métier de trajet. Nous n’exigeons l’extrait de casier judiciaire que pour deux métiers de notre catalogue, les plus sensibles, et nous ne prétendons pas le demander à tous. Sur ces deux mêmes métiers, nous tirons au sort un contrôle facial, entre 24 et 72 heures après le précédent : un professionnel qui n’est pas enrôlé, qui échoue deux fois d’affilée ou qui retire son consentement sort de la file des missions. Nous ne vous promettons aucun délai : le compte s’ouvre dès que le dossier est complet.',
                ],
                [
                    'q' => 'Comment gagnez-vous de l’argent ?',
                    'r' => 'Nous prenons aujourd’hui 15 % du montant de l’intervention. Nous le prenons sur la part du professionnel, et nous ne l’ajoutons pas à votre prix. Un plancher de 2 € s’applique par encaissement : sur un trajet de 4,81 €, il monte à 41 %. Sur une intervention de 150 €, il ne se voit pas. Plus le montant est petit, plus ce plancher pèse — nous préférons le dire que le cacher. Nous ne prélevons rien tant que l’intervention n’est pas terminée. Sur les pourboires, nous ne retenons rien aujourd’hui.',
                ],
                [
                    'q' => 'Et si personne n’accepte ma demande ?',
                    'r' => 'L’intervention immédiate n’existe pas sur tout le catalogue : :immediat métiers l’autorisent, et seulement là où la zone l’ouvre. Quand elle est ouverte, elle coûte 30 % de plus, annoncé avant que vous confirmiez. Votre demande part alors vers un seul professionnel à la fois, qui a 20 secondes pour répondre — 30 secondes en plomberie et en électricité. La recherche s’élargit de 5 kilomètres à chaque vague, sans dépasser 20 kilomètres ; au rayon maximal, elle part vers vingt professionnels en même temps, et le premier qui accepte prend la mission. Nous ne touchons pas à votre carte : nous ne prenons l’empreinte qu’après l’acceptation. Tant que personne n’a accepté, vous annulez gratuitement, sans limite de temps ; une fois un professionnel lancé vers vous, vous gardez 3 minutes pour annuler sans frais, puis l’annulation coûte 5 €. Sur un rendez-vous planifié, chaque professionnel a 30 minutes, et la demande passe au suivant jusqu’à cinq fois. Si nous ne servons pas encore votre adresse, nous vous le disons au moment de confirmer, avec le motif exact — zone non desservie, ou métier pas encore ouvert chez vous — au lieu de vous laisser attendre un professionnel qui ne viendrait pas.',
                ],
            ],
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
