<?php

/*
|--------------------------------------------------------------------------
| Répartition INTERNE — entre les salariés d'une même société prestataire
|--------------------------------------------------------------------------
|
| RIEN À VOIR AVEC `MatchingScoreEngine`, et la confusion coûterait cher. Celui-là choisit un
| PRESTATAIRE sur la place de marché : réputation, prix, distance, acceptation. Ici, la société est
| déjà choisie et il s'agit de savoir lequel de SES employés y va. Les critères n'ont ni le même
| sens ni la même légitimité — on ne classe pas ses propres salariés sur leur note client.
|
| LA DISPONIBILITÉ N'EST PAS UN SCORE, C'EST UN FILTRE. Quelqu'un déjà pris à cette heure-là ne
| descend pas dans le classement : il en sort. Le pondérer laisserait un très bon score compenser
| une impossibilité physique, et enverrait la même personne à deux endroits.
|
| Les poids sont ici pour être RELUS ET DISCUTÉS par qui exploite le produit — c'est la raison
| d'être d'un fichier de configuration plutôt que de constantes enfouies dans le moteur.
*/

return [

    /*
     * Le référent du site pèse le plus lourd, et c'est le cœur du sujet.
     *
     * Une société qui dessert vingt immeubles y place des habitués : celui qui connaît le code de
     * la porte, l'ascenseur en panne, l'étage à ne pas déranger avant 10 h. C'est la connaissance
     * la plus chère à reconstituer, et la seule que le client remarque.
     */
    'poids' => [
        'referent_site_lead' => 40,
        'referent_site_backup' => 20,

        /*
         * La charge du jour, en NÉGATIF. À disponibilité égale, celui qui a déjà trois missions
         * passe après celui qui en a une : c'est une règle d'équité autant que de fiabilité — une
         * journée trop chargée finit en retard.
         */
        'charge_par_mission' => -5,

        /*
         * La rotation : un point par jour écoulé depuis la dernière mission, plafonné.
         *
         * Sans elle, le moteur choisirait toujours le même — le mieux placé le reste, son score ne
         * bouge pas, et l'équipe se partage entre surchargés et oubliés. Le plafond évite qu'une
         * longue absence (congé, arrêt) ne fasse d'un revenant le candidat imbattable.
         */
        'rotation_par_jour' => 1,
        'rotation_plafond' => 15,

        /*
         * Le métier : un peintre sur une mission de peinture. Moins lourd que le référent parce que
         * la plupart des sociétés de ce produit sont mono-métier — quand ce n'est pas le cas, le
         * critère tranche, sinon il ne départage rien.
         */
        'metier' => 15,

        /*
         * L'agence de rattachement. Déclarée ici et VOLONTAIREMENT INACTIVE : l'entité `agences`
         * naît au lot 6. La laisser à 0 jusque-là évite qu'un score dépende d'une colonne qui
         * n'existe pas, tout en gardant la dimension visible pour qui lit ce fichier.
         */
        'agence' => 0,
        'agence_apres_lot_6' => 10,
    ],

    /*
     * Sans fin déclarée, une mission occupe deux heures. La question posée est « déjà pris à ce
     * moment-là », pas « combien de temps exactement ».
     */
    'duree_par_defaut_heures' => 2,

    /*
     * Le verrou du job d'auto-assignation, en secondes.
     *
     * `ShouldBeUnique` empêche deux exécutions concurrentes pour la même société : un double-clic
     * sur le bouton ne doit pas assigner deux fois le même arriéré. 300 s couvre largement un
     * traitement normal, et libère la voie si le processus meurt.
     */
    'verrou_job_secondes' => 300,

    /*
     * Combien de missions un passage automatique traite au plus.
     *
     * UNE BORNE EXPLICITE, ET ELLE SE JOURNALISE. Un arriéré de plusieurs milliers de missions
     * bloquerait la file ; s'arrêter en silence ferait croire le travail terminé. Le job dit
     * combien il a laissé.
     */
    'lot_maximum' => 200,
];
