<?php

/*
 * LES DURÉES QUI BORNENT L'INTERVENTION, EN CONFIGURATION ET PAS EN DUR.
 *
 * Elles se régleront sur les données réelles une fois le trafic présent. Les écrire dans le code
 * obligerait à un déploiement pour changer un nombre que l'exploitation doit pouvoir ajuster.
 */
return [
    /*
     * LA FENÊTRE D'ÉDITION DE LA TO-DO LIST DU CLIENT.
     *
     * Comptée depuis le DÉMARRAGE de la mission, pas depuis la réservation : avant que le
     * prestataire ait commencé, le client peut écrire sans contrainte — personne ne travaille
     * encore. C'est une fois quelqu'un chez lui, gants aux mains, qu'une liste qui s'allonge
     * devient un piège.
     */
    'todo_window_minutes' => (int) env('MISSION_TODO_WINDOW_MINUTES', 30),

    /*
     * CE QUE CHAQUE TÂCHE AJOUTÉE ROUVRE AU PRESTATAIRE POUR RÉVISER SON DEVIS.
     *
     * La symétrie est la règle. Sans elle, un client ajoute trois tâches lourdes à la minute 25,
     * quand la fenêtre de révision est déjà close — et la garde anti-abus prestataire devient une
     * arme entre ses mains.
     */
    'requote_reopen_minutes' => (int) env('MISSION_REQUOTE_REOPEN_MINUTES', 6),

    /*
     * SUR COMBIEN DE TEMPS L'ARBITRE REGARDE EN ARRIERE.
     *
     * Trop court, un tricheur regulier repart a zero entre deux campagnes. Trop long, une erreur
     * d'il y a deux ans pese encore. Quatre-vingt-dix jours : assez pour qu'un motif se dessine,
     * assez court pour qu'on puisse se corriger.
     */
    'arbitration_window_days' => (int) env('MISSION_ARBITRATION_WINDOW_DAYS', 90),
];
