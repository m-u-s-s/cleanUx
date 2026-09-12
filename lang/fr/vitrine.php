<?php

/*
 * Les textes de la vitrine : le film du parcours d'une mission, et l'invitation à télécharger
 * les applications. Ils passent par `__()` pour être modifiables depuis /admin/traductions
 * sans toucher au code — le centre i18n écrit dans `translation_overrides`.
 */

return [

    'accueil' => [
        'bascule' => [
            'client' => 'Je cherche un professionnel',
            'prestataire' => 'Je propose mes services',
            'aide' => 'Un même compte vous ouvre les deux côtés : vous réservez aujourd’hui, vous acceptez des missions demain.',
        ],
        'hero' => [
            'titre' => 'Avec Brio, tout le monde gagne de l’argent',
            'sous_titre' => 'Faites venir un pro chez vous en quelques questions. Et de l’autre côté, gagnez de l’argent : vos compétences, votre voiture, votre logement. Un seul compte pour les deux.',
            'puces' => [
                'Le prix s’affiche avant que vous donniez votre nom',
                'Votre voiture et votre logement peuvent vous rapporter',
                'Un pro garde 85 % de ce qu’il facture',
            ],
            'bouton' => 'Voir mon estimation',
            'bouton_secondaire' => 'Je propose mes services',
            'note' => 'Ouvert en Belgique, dans :zones régions, sur :metiers métiers.',
        ],
        'metiers' => [
            'libelle' => 'Les métiers ouverts sur Brio',
            'defiler' => 'Faites défiler',
            'client' => [
                'surtitre' => 'Ce que vous pouvez commander',
                'titre' => ':metiers métiers.<br><span>Six secteurs.</span>',
                'texte' => 'Ce chiffre est celui du catalogue, lu au moment où vous chargez cette page. Il monte quand un métier ouvre, il descend quand un métier ferme.',
                'fin_titre' => 'Et le reste du catalogue',
                'fin_texte' => 'Garde d’enfants, déménagement, levage, gardiennage, courses d’un point à un autre.',
                'bouton' => 'Voir mon estimation',
            ],
            'prestataire' => [
                'surtitre' => 'Où vous pouvez travailler',
                'titre' => ':metiers métiers.<br><span>Choisissez les vôtres.</span>',
                'texte' => 'Vous déclarez vos métiers et vos zones à l’inscription. Vous ne recevez que les missions qui y correspondent, et jamais deux offres en même temps.',
                'fin_titre' => 'Votre métier n’est pas là ?',
                'fin_texte' => 'Le catalogue s’ouvre métier par métier et zone par zone. Inscrivez-vous : vous recevez les missions dès que le vôtre ouvre près de chez vous.',
                'bouton' => 'Recevoir des missions',
            ],
            'secteurs' => [
                [
                    'icone' => 'sparkles',
                    'fond' => 'maison',
                    'titre' => 'Maison et ménage',
                    'metiers' => 'Nettoyage à domicile, vitres, fin de chantier.',
                    'client' => 'Le nettoyage à domicile accepte l’intervention immédiate',
                    'prestataire' => 'Des missions courtes, souvent récurrentes, dans votre quartier',
                ],
                [
                    'icone' => 'wrench',
                    'fond' => 'travaux',
                    'titre' => 'Travaux et rénovation',
                    'metiers' => 'Peinture, plomberie, bâtiment, rénovation.',
                    'client' => 'Commandés ensemble, ils s’enchaînent dans le bon ordre',
                    'prestataire' => 'Sur un chantier à plusieurs métiers, votre place est réservée d’avance',
                ],
                [
                    'icone' => 'bolt',
                    'fond' => 'exterieur',
                    'titre' => 'Extérieur et technique',
                    'metiers' => 'Électricité, jardinage, toiture, élagage.',
                    'client' => 'La toiture et l’élagage passent par un devis, et le disent d’entrée',
                    'prestataire' => 'Vous chiffrez vous-même les métiers qui passent par devis',
                ],
            ],
        ],
        'client' => [
            'accroche' => 'Vous lisez la fourchette avant de donner votre nom, et personne n’entre chez vous sans le code à six chiffres que vous seul connaissez.',
            'blocs' => [
                [
                    'cle' => 'client-pourquoi',
                    'surtitre' => 'Pourquoi Brio',
                    'titre' => 'Six mécanismes, pas six promesses',
                    'sous_titre' => 'Chaque étape que vous lisez ici porte son chiffre, son code ou sa preuve horodatée.',
                    'bouton' => 'Voir ma fourchette de prix',
                    'cartes' => [
                        [
                            'titre' => 'Le prix s’affiche avant votre nom',
                            'texte' => 'Ici, aucun formulaire ne précède le premier chiffre. Sur les métiers tarifés de votre zone, vous lisez la fourchette avant de donner votre nom. Le bas de cette fourchette engage Brio. La toiture et l’élagage passent par devis : vous n’y voyez aucun prix avant l’étude.',
                            'pastille' => 'Prix d’abord, nom ensuite',
                        ],
                        [
                            'titre' => 'Empreinte au départ, débit à la fin',
                            'texte' => 'Ici, votre carte laisse une simple empreinte dès que le professionnel accepte. La somme part quand le chantier est terminé, jamais au démarrage. Au-delà de 500 €, vous versez 30 % à la commande. Le reste attend la fin du chantier.',
                            'pastille' => 'Débit à la fin du chantier',
                        ],
                        [
                            'titre' => 'Six chiffres que vous seul connaissez',
                            'texte' => 'Ici, le professionnel ne déclare pas lui-même son passage. Vous recevez six chiffres, valables vingt minutes. Vous les donnez à l’arrivée : sans eux, l’intervention ne démarre pas. Sur une course d’un point à un autre, votre montée à bord lance le trajet.',
                            'pastille' => '6 chiffres, 20 minutes',
                        ],
                        [
                            'titre' => 'Chaque photo porte son heure et sa signature',
                            'texte' => 'Une contestation ne finit pas en parôle contre parôle. Chaque photo porte son heure, sa signature et sa position, calculées au dépôt. Seul le professionnel qui vient chez vous peut en ajouter.',
                            'pastille' => 'Heure, position, signature',
                        ],
                        [
                            'titre' => 'Plusieurs métiers, une seule commande',
                            'texte' => 'Une seule commande regroupe les métiers de votre chantier, et ils s’enchaînent dans le bon ordre. La remise passe de 5 % pour deux métiers à 8 % pour trois, puis 12 % à partir de quatre.',
                            'pastille' => '-5 %, -8 %, -12 %',
                        ],
                        [
                            'titre' => 'Vous lisez le barème avant de réserver',
                            'texte' => 'Annuler ne coûte rien jusqu’à 48 h avant le rendez-vous. Ensuite : 25 % jusqu’à 24 h, 50 % jusqu’à 2 h, puis la totalité. Un plancher de 5 % s’applique dès que le professionnel est en route.',
                            'pastille' => '48 h / 24 h / 2 h',
                        ],
                    ],
                ],
                [
                    'cle' => 'client-profils',
                    'surtitre' => 'Particuliers et entreprises',
                    'titre' => 'Chez vous ou dans votre entreprise',
                    'sous_titre' => 'Une intervention ponctuelle, un rendez-vous à la date de votre choix, plusieurs locaux, un contrat négocié : vous choisissez le cadre.',
                    'bouton' => 'Composer ma demande',
                    'cartes' => [
                        [
                            'titre' => 'Chez vous, une fois, tout de suite',
                            'texte' => 'Sur :immediat métiers, et dans les zones qui l’ouvrent, votre demande part vers un seul professionnel qui a 20 s pour répondre. La recherche s’élargit de 5 km en 5 km jusqu’à 20 km. Cette rapidité coûte 30 % de plus, annoncés avant que vous confirmiez.',
                            'pastille' => '+30 %, 20 s par offre, 30 s selon le métier',
                        ],
                        [
                            'titre' => 'Chez vous, à la date que vous fixez',
                            'texte' => 'Votre rendez-vous part vers un seul professionnel, qui a 30 minutes pour répondre, puis passe au suivant jusqu’à cinq fois. Un professionnel déjà venu chez vous pèse 10 % dans le classement de ceux à qui l’offre est envoyee.',
                            'pastille' => 'Vos rendez-vous passés : 10 % du classement',
                        ],
                        [
                            'titre' => 'Votre entreprise, plusieurs locaux',
                            'texte' => 'Votre espace reunit vos locaux, vos réservations et vos membres sur quinze écrans. Chaque membre reçoit un rôle parmi six, et vous pouvez le limiter à une liste de locaux. Un budget par local prévient au seuil que vous fixez, sans bloquer la réservation.',
                            'pastille' => 'Le seuil prévient, il ne bloque pas',
                        ],
                        [
                            'titre' => 'Votre entreprise, un contrat-cadre',
                            'texte' => 'Votre contrat fixe un prix ligne par ligne, ou une remise unique. Vous choisissez le délai de paiement, vous importez vos réservations par fichier, et vous téléchargez votre comptabilité en tableur ou au format des écritures comptables.',
                            'pastille' => 'Délai de paiement : 0 à 365 jours',
                        ],
                    ],
                ],
                [
                    'cle' => 'client-confiance',
                    'surtitre' => 'Qui entre chez vous',
                    'titre' => 'Rien ne commence sans votre code',
                    'sous_titre' => 'Vous donnez le départ de l’intervention. L’arrivée, la photo et la réclamation laissent chacune une trace datée.',
                    'bouton' => 'Voir le déroulé d’une intervention',
                    'cartes' => [
                        [
                            'titre' => 'Le code de départ reste chez vous',
                            'texte' => 'Vous recevez six chiffres, valables vingt minutes. Le professionnel ne lance l’intervention qu’avec ce code. Sur un trajet, votre montée à bord lance la course.',
                            'pastille' => '6 chiffres, 20 minutes',
                        ],
                        [
                            'titre' => 'L’arrivée se vérifie à 250 mètres',
                            'texte' => 'À l’arrivée, le téléphone du professionnel doit se trouver dans un rayon de 250 mètres autour de votre adresse. Un signal imprécis élargit ce rayon. Sans signal, l’arrivée porte la mention sans position.',
                            'pastille' => 'rayon de 250 m',
                        ],
                        [
                            'titre' => 'Chaque photo porte sa signature et son heure',
                            'texte' => 'Chaque photo reçoit une signature, une heure et un lieu au moment du dépôt. Cette signature fige l’image telle qu’elle a été déposée. Seul le professionnel qui vient chez vous peut en ajouter. Chaque photo de l’intervention porte donc le même marquage.',
                            'pastille' => 'signature, heure, lieu',
                        ],
                        [
                            'titre' => 'Une réclamation gèle le versement',
                            'texte' => 'Votre réclamation part avec son niveau d’urgence. Le dossier monte d’un cran tout seul après 24 h, puis une seconde fois après 48 h. Pendant ce temps, le professionnel ne touche pas son argent.',
                            'pastille' => 'Le dossier monte à 24 h puis 48 h',
                        ],
                    ],
                ],
            ],
            'questions_titre' => 'Quatre questions qu’on nous pose',
            'questions' => [
                [
                    'q' => 'Et si le travail est mal fait ?',
                    'r' => 'Vous ouvrez une réclamation depuis l’intervention, avec ses photos horodatées et la trace de l’arrivée. Tant que la réclamation dure, le professionnel ne touche pas son argent. Le dossier monte d’un cran tout seul après 24 heures, puis une seconde fois après 48 heures. Il se referme sur l’une des dix issues prévues. Nous ne vous promettons aucun délai de réponse humaine : ce qui est tenu, c’est cette montée automatique.',
                ],
                [
                    'q' => 'Et si quelque chose casse chez moi ?',
                    'r' => 'Disons-le franchement : Brio ne vous assure pas. Aucune assurance n’est comprise dans votre réservation, et nous ne prenons aucun dégât en charge. Ce que Brio vous donne, ce sont des preuves : l’heure et la position de l’arrivée dans un rayon de 250 mètres, chaque photo signée au moment du dépôt, et une réclamation qui gèle le versement du professionnel jusqu’à son issue. Pour être couvert, vérifiez votre propre contrat d’habitation et celui du professionnel.',
                ],
                [
                    'q' => 'Combien je perds si j’annule ?',
                    'r' => 'Jusqu’à 48 heures avant le rendez-vous, rien. Ensuite vous payez 25 % jusqu’à 24 heures avant, 50 % jusqu’à 2 heures avant, puis 100 %. Un plancher de 5 % s’applique dès que le professionnel est en route, y compris dans la fenêtre gratuite. La force majeure et l’urgence médicale vous exemptent sur justificatif, l’urgence médicale deux fois par 30 jours. Sur une demande immédiate, vous arrêtez sans frais tant que personne n’a accepté, puis pendant trois minutes encore. Au-delà, l’arrêt coûte 5 €.',
                ],
                [
                    'q' => 'Et si personne n’accepte ma demande ?',
                    'r' => 'Sur une demande immédiate, la recherche part de cinq kilomètres et s’élargit de cinq en cinq jusqu’à vingt kilomètres. À ce dernier rayon, vingt professionnels reçoivent l’offre en même temps. Sur un rendez-vous à date fixée, l’offre passe d’un professionnel au suivant jusqu’à cinq fois, puis elle revient d’office au mieux placé qui n’a pas refusé. Si personne n’accepte, rien ne part de votre carte : l’empreinte n’est posée que lorsqu’un professionnel accepte.',
                ],
            ],
            'final_titre' => 'Voyez votre prix avant de donner votre nom',
            'final_texte' => 'Le parcours de commande s’ouvre sans compte. Vous répondez aux questions de votre métier, le montant bouge, et vous décidez ensuite.',
            'final_bouton' => 'Voir mon estimation',
            'final_autre' => 'Je veux plutôt gagner de l’argent',
        ],
        'prestataire' => [
            'accroche' => 'Vous gardez 85 % du montant, sans abonnement ni achat de contact, et vous décidez seul des missions que vous acceptez.',
            'blocs' => [
                [
                    'cle' => 'presta-gagner',
                    'surtitre' => 'Vos revenus',
                    'titre' => 'Ce que chaque mission vous rapporte',
                    'sous_titre' => 'Le montant de votre mission vous arrive avec sa commission. Brio la prélève sur votre part, jamais sur le prix du client.',
                    'bouton' => 'Créer mon compte professionnel',
                    'cartes' => [
                        [
                            'titre' => 'Vous gardez 85 % du montant encaissé',
                            'texte' => 'Brio prélève 15 % du montant, avec un plancher de 2 € par encaissement. Cette commission sort de votre part, jamais du prix annoncé à votre client. Sur une mission à 200 €, vous encaissez donc 170 €. Sur les petits encaissements, c’est le plancher de 2 € qui s’applique.',
                            'pastille' => '85 %, plancher 2 €',
                        ],
                        [
                            'titre' => 'Vous ne payez ni abonnement ni contact',
                            'texte' => 'Vous ne payez aucun abonnement. Vous n’achetez aucun contact. Brio prend sa commission sur les encaissements de la mission, jamais sous la forme d’un frais fixe à l’avance.',
                            'pastille' => 'Sans abonnement',
                        ],
                        [
                            'titre' => 'Votre argent part quand la mission finit',
                            'texte' => 'À la fin de la mission, votre part rejoint votre compte de paiement Stripe. Une réclamation en cours gèle ce versement jusqu’à son issue. Stripe vire ensuite vers votre banque selon son propre calendrier. Stripe fixe ce calendrier, pas nous.',
                            'pastille' => 'Dès la fin de la mission',
                        ],
                        [
                            'titre' => 'Le pourboire vous revient en entier',
                            'texte' => 'Votre client dispose de sept jours pour ajouter un pourboire après la mission. Brio n’en prélève rien. Vous touchez la somme entière.',
                            'pastille' => '0 % prélevé',
                        ],
                        [
                            'titre' => 'Vous louez aussi votre véhicule ou votre logement',
                            'texte' => 'Vous louez votre véhicule ou votre logement aux autres membres. Brio prélève 25 % du loyer. Vous gardez les 75 % restants. Vous fixez vos dates. Vous validez chaque demande. L’état des lieux se fait en photos horodatées à la remise et au retour.',
                            'pastille' => '75 % pour vous',
                        ],
                        [
                            'titre' => '10 € à la première mission de votre filleul',
                            'texte' => 'Vous partagez votre lien de parrainage. Votre filleul termine sa première mission. Votre compte reçoit alors 10 € de crédit.',
                            'pastille' => '10 € de crédit',
                        ],
                    ],
                ],
                [
                    'cle' => 'presta-missions',
                    'surtitre' => 'Recevoir des missions',
                    'titre' => 'Comment les missions arrivent, et qui décide',
                    'sous_titre' => 'Vous êtes sollicité un à un, et en groupe au dernier rayon. Neuf mesures décident du classement. Les badges ne comptent pour rien.',
                    'bouton' => 'Recevoir mes premières missions',
                    'cartes' => [
                        [
                            'titre' => 'Une offre à la fois, jusqu’au dernier rayon',
                            'texte' => 'Une mission planifiée vous laisse 30 minutes pour répondre ; une intervention immédiate, 20 s — 30 s en plomberie, électricité, toiture et déménagement. Vous êtes sollicité un à un, sauf au dernier rayon de 20 km.',
                            'pastille' => '30 min planifié, 20 à 30 s immédiat',
                        ],
                        [
                            'titre' => 'Refuser vous retire, laisser expirer non',
                            'texte' => 'Un refus exprimé vous retire définitivement de cette mission. Laisser le délai expirer ne vous en retire pas. Vous restez candidat aux tours suivants.',
                            'pastille' => 'Refuser retire, laisser passer non',
                        ],
                        [
                            'titre' => 'Vos métiers déclarés commandent vos offres',
                            'texte' => 'Vous déclarez vos métiers et vos zones à l’inscription. L’intervention immédiate tient à deux conditions à la fois : elle ne concerne que :immediat métiers, et seulement là où la zone l’ouvre.',
                            'pastille' => 'Métiers déclarés à l’inscription',
                        ],
                        [
                            'titre' => 'Ce qui décide de votre place',
                            'texte' => 'Neuf mesures fixent votre place dans la file. La note pèse 25 %, le taux d’acceptation et la proximité 15 % chacun. Les badges affichés sur votre profil n’entrent dans aucune de ces neuf mesures.',
                            'pastille' => 'Note 25 %, badges 0 %',
                        ],
                        [
                            'titre' => 'En ligne veut dire trois choses',
                            'texte' => 'Trois conditions vous rendent joignable : connexion ouverte, position connue, signal de moins de cinq minutes. Sans les trois, aucune offre immédiate ne vous parvient. Un signal vieux de plus de cinq minutes vous sort de la file immédiate.',
                            'pastille' => 'Les trois, sinon rien',
                        ],
                        [
                            'titre' => 'Six étapes pour vous inscrire',
                            'texte' => 'L’inscription compte six étapes obligatoires. Les pièces demandées dépendent des métiers que vous déclarez. Le casier judiciaire concerne deux métiers seulement : la garde d’enfants et le gardiennage. Nous ne vous promettons aucun délai d’approbation.',
                            'pastille' => '6 étapes obligatoires',
                        ],
                    ],
                ],
                [
                    'cle' => 'presta-societe',
                    'surtitre' => 'Seul ou avec vos équipes',
                    'titre' => 'Pilotez votre société depuis Brio',
                    'sous_titre' => 'Vous gérez vos rôles, vos équipes, vos sites, vos plannings et vos heures au même endroit. Chaque employé garde son écran de terrain.',
                    'bouton' => 'Inscrire ma société',
                    'cartes' => [
                        [
                            'titre' => 'Onze rôles, vous réglez chaque droit',
                            'texte' => 'Votre société compte onze rôles, du responsable qualité au chef d’équipe. Vous réglez chaque droit case par case, rôle par rôle, et vous y revenez quand vous voulez.',
                            'pastille' => '11 rôles',
                        ],
                        [
                            'titre' => 'Vos implantations, vos sites, vos équipes de terrain',
                            'texte' => 'Vous déclarez vos implantations une par une. Vous déclarez à part les sites clients que vous desservez, avec le référent que vous y placez. Vous composez vos équipes de terrain dans le même espace. Vous désignez leur chef.',
                            'pastille' => 'Implantation, site, équipe',
                        ],
                        [
                            'titre' => 'Le planning et les heures de vos employés',
                            'texte' => 'Le planning de vos employés tient dans un seul écran. Chacun pose ses disponibilités depuis son propre espace. Vous relevez les heures pointées dans l’écran prévu pour ça, avec ce qu’elles coûtent.',
                            'pastille' => 'Planning et heures',
                        ],
                        [
                            'titre' => 'Vous répartissez les missions entre vos employés',
                            'texte' => 'Vous répartissez les missions de votre société depuis votre écran de répartition. Vous attribuez chaque mission à l’employé de votre choix. Vous suivez vos échanges et vos tâches au même endroit.',
                            'pastille' => 'Répartition interne',
                        ],
                        [
                            'titre' => 'Ce que voit votre employé sur le terrain',
                            'texte' => 'Votre employé ouvre son propre espace : ses missions, son planning, ses disponibilites, ses revenus, son portefeuille et ses avis. Ce qu’il voit dépend des droits que vous lui laissez, case par case.',
                            'pastille' => 'Son espace à lui',
                        ],
                        [
                            'titre' => 'Les consommables et les devis de chantier',
                            'texte' => 'Vous suivez votre stock de consommables dans son écran, alimenté depuis le terrain. Votre société bâtit ses propres devis, et vos employés suivent leurs demandes de devis de chantier depuis le leur.',
                            'pastille' => 'Toiture et élagage sur devis',
                        ],
                    ],
                ],
            ],
            'questions_titre' => 'Quatre questions qu’on nous pose',
            'questions' => [
                [
                    'q' => 'Combien ça me coûte ?',
                    'r' => 'Aucun abonnement, aucun achat de contact. Brio prélève 15 % du montant de la mission, avec un plancher de 2 € par encaissement, et vous gardez 85 %. Cette commission sort de votre part, jamais du prix annoncé au client. Sur une location entre membres, Brio prélève 25 % et vous gardez 75 %. Sur un pourboire, Brio ne prélève rien.',
                ],
                [
                    'q' => 'Quand suis-je payé ?',
                    'r' => 'À la fin de la mission, votre part rejoint votre compte de paiement Stripe. Stripe vire ensuite vers votre banque selon son propre calendrier, et nous ne vous promettons aucun délai. Une réclamation en cours gèle ce versement jusqu’à son issue. Au-delà de 500 €, le client verse 30 % à la commande et le reste attend la fin du chantier.',
                ],
                [
                    'q' => 'Qui décide des missions que je reçois ?',
                    'r' => 'Vos métiers déclarés et votre position commandent les offres qui vous parviennent. Neuf mesures fixent ensuite votre place dans la file : la note 25 %, le taux d’acceptation et la proximité 15 % chacun, le taux de missions terminées, la charge et l’affinité avec le client 10 % chacun, le temps de réponse, la spécialité métier et l’équilibrage 5 % chacun. Les badges n’entrent dans aucune de ces neuf mesures. Vous refusez quand vous voulez : un refus exprimé vous retire de cette mission, laisser le délai expirer ne vous en retire pas.',
                ],
                [
                    'q' => 'Et si un client m’accuse à tort ?',
                    'r' => 'Vos preuves partent du terrain. L’arrivée porte son heure et sa position, dans un rayon de 250 mètres autour de l’adresse. Chaque photo reçoit sa signature au moment du dépôt, et vous seul, qui intervenez sur la mission, pouvez en déposer. Pendant l’examen, votre versement reste gelé : le dossier monte d’un cran après 24 heures, puis une seconde fois après 48 heures, et il se referme sur l’une des dix issues prévues.',
                ],
            ],
            'final_titre' => 'Inscrivez-vous, vous ne devez rien tant que vous n’avez rien gagné',
            'final_texte' => 'Pas d’abonnement, pas d’achat de contacts. Vous déclarez vos métiers et vos zones, et vous recevez les missions qui y correspondent.',
            'final_bouton' => 'Recevoir des missions',
            'final_autre' => 'Je cherche plutôt un professionnel',
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
