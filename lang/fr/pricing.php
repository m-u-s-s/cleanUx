<?php

/**
 * LA RÈGLE DU TEMPS, ÉCRITE UNE FOIS.
 *
 * POURQUOI CE FICHIER EXISTE. La facturation à l'heure et sa majoration de dépassement devaient
 * être annoncées à sept endroits : le sélecteur d'heures, la confirmation de commande, les
 * conditions générales du client, le contrat prestataire, la page publique du métier, l'écran de
 * suivi du client et l'écran de terrain du prestataire. Sept rédactions séparées, c'est sept
 * règles — et le jour où le multiplicateur change, six d'entre elles mentent.
 *
 * LES CHIFFRES NE SONT PAS ÉCRITS EN TOUTES LETTRES, ils sont interpolés depuis
 * `config/order_engine.php`. Un « ×1,30 » tapé dans une phrase survit à un changement de
 * configuration, et c'est exactement ainsi qu'une plateforme finit par afficher une règle qu'elle
 * n'applique plus. La source du nombre est celle qui facture.
 *
 * DEUX LONGUEURS, PAS UNE. `rule_short` tient sous un sélecteur ou dans un bandeau ; `rule_full`
 * est la version contractuelle, qui nomme la franchise et le plafond. Les surfaces courtes ne
 * doivent pas tronquer la longue — une règle coupée en son milieu dit autre chose.
 *
 * LES TEXTES SERVIS PAR L'API vivent ici et nulle part ailleurs, sur le modèle de `face_check.php` :
 * l'application mobile doit afficher EXACTEMENT la même phrase que le web. Deux copies d'un texte
 * qui engage de l'argent finiraient par diverger, et c'est celle qu'on n'a pas relue qui serait
 * lue par le client.
 */
return [

    'hourly' => [

        // ── L'annonce de la règle ────────────────────────────────────────────
        'rule_short' => 'Vous choisissez votre nombre d’heures et pouvez le prolonger à tout moment, '
            .'avant ou pendant l’intervention, au tarif normal. Les heures effectuées au-delà sans '
            .'prolongation sont facturées :multiplier fois le tarif horaire, après :grace minutes de '
            .'tolérance.',

        'rule_full' => 'Cette prestation est facturée au temps passé. Vous choisissez le nombre '
            .'d’heures à la commande et pouvez le prolonger à tout moment — avant comme pendant '
            .'l’intervention — au tarif horaire normal ; seules les heures réellement prestées sont '
            .'dues. Si l’intervention se prolonge au-delà du temps acheté sans que vous l’ayez '
            .'étendu, les :grace premières minutes sont offertes, puis chaque quart d’heure entamé '
            .'est facturé :multiplier fois le tarif horaire — cette majoration s’ajoute aux '
            .'majorations éventuellement déjà appliquées (intervention immédiate, nuit, week-end). '
            .'Le dépassement facturable ne peut jamais excéder la durée initialement commandée.',

        // ── Le prestataire — même règle, autre conséquence ───────────────────
        'rule_provider' => 'Les prestations facturées à l’heure sont vendues pour une durée précise, '
            .'que le client peut prolonger à tout moment. Au-delà de cette durée, et passé :grace '
            .'minutes de tolérance, le temps supplémentaire est facturé au client :multiplier fois le '
            .'tarif horaire. Vous êtes rémunéré à votre tarif normal sur ce temps ; la majoration '
            .'revient à la plateforme. Prévenez le client avant la fin du temps acheté : il peut '
            .'prolonger sans majoration, et c’est dans son intérêt comme dans le vôtre.',

        // ── Les libellés courts, pour les compteurs et les récapitulatifs ────
        'remaining' => 'Temps restant',
        'overrun' => 'Temps dépassé',
        'grace_running' => 'Fin de la tolérance',
        'purchased' => 'Temps réservé',
        'extend' => 'Prolonger',
        'extend_hint' => 'Au tarif normal, sans majoration.',
        'extended_notice' => 'Temps prolongé. Seules les heures réellement prestées sont dues.',
        'overtime_line' => 'Dépassement — :minutes min au tarif majoré',
        'capped_notice' => 'Le dépassement facturable a atteint son plafond : la durée commandée.',
    ],
];
