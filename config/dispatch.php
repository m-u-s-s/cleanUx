<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TTL d'une offre immédiate (secondes)
    |--------------------------------------------------------------------------
    |
    | Combien de temps un prestataire a pour accepter ou refuser, avant que la
    | mission ne passe automatiquement au suivant.
    |
    | VINGT SECONDES, et c'est un choix produit, pas une limite technique. C'est
    | la fenêtre des plateformes VTC : assez pour lire le métier, la distance et
    | la rémunération ; trop court pour aller consulter son agenda. Plus long, le
    | client attend derrière sa porte pendant qu'on interroge une chaîne de gens
    | qui ne répondront pas.
    |
    | Surchargeable PAR MÉTIER : un toiturier doit lire une description de
    | chantier avant de s'engager, un babysitter n'a rien à lire.
    */
    'default_timeout' => (int) env('DISPATCH_DEFAULT_TIMEOUT', 20),

    'timeout_per_trade' => [
        // Métiers qui ont besoin de plus de temps (description de chantier, vérification d'outils…)
        'toiturier' => (int) env('DISPATCH_TIMEOUT_TOITURIER', 30),
        'electricite' => (int) env('DISPATCH_TIMEOUT_ELECTRICITE', 30),
        'plomberie' => (int) env('DISPATCH_TIMEOUT_PLOMBERIE', 30),
        'demenagement' => (int) env('DISPATCH_TIMEOUT_DEMENAGEMENT', 30),

        // Métiers à réponse immédiate
        'nettoyage' => (int) env('DISPATCH_TIMEOUT_NETTOYAGE', 20),
        'babysitting' => (int) env('DISPATCH_TIMEOUT_BABYSITTING', 20),
        'jardinage' => (int) env('DISPATCH_TIMEOUT_JARDINAGE', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | TTL d'une offre PLANIFIÉE (secondes)
    |--------------------------------------------------------------------------
    |
    | Patron « Uber Reserve » : le rendez-vous part à l'avance chez le mieux
    | classé, avec une fenêtre de réponse longue. Personne n'attend derrière la
    | porte — le compter en secondes n'aurait aucun sens, et forcerait des refus
    | de gens simplement occupés.
    |
    | Trente minutes par défaut. Au-delà, on escalade ; à l'épuisement de la
    | chaîne, l'assignation d'office reprend la main (comportement historique,
    | devenu le REPLI et non plus la règle).
    */
    'scheduled_offer_timeout_seconds' => (int) env('DISPATCH_SCHEDULED_TIMEOUT', 1800),

    /*
    |--------------------------------------------------------------------------
    | Fraîcheur de position exigée (minutes)
    |--------------------------------------------------------------------------
    |
    | Au-delà, le prestataire n'est plus considéré comme joignable, quel que soit
    | son état déclaré. `provider_profiles.is_online` reste vrai quand
    | l'application est morte depuis vingt minutes : c'est un drapeau qu'on pose,
    | pas un signe de vie. Le battement de `provider_presence`, lui, s'arrête.
    |
    | Aligné sur `presence.stale_after_minutes` : deux seuils différents feraient
    | qu'un prestataire passé automatiquement hors ligne resterait candidat, ou
    | l'inverse.
    */
    'position_freshness_minutes' => (int) env('DISPATCH_POSITION_FRESHNESS_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Les vagues : un rayon qui grandit
    |--------------------------------------------------------------------------
    |
    | On commence PRÈS, parce que la proximité est ce que le client attend, et on
    | élargit quand personne ne répond. Le maximum est une limite produit :
    | au-delà, on enverrait quelqu'un à quarante kilomètres pour une intervention
    | d'une heure, et le client attendrait un trajet qu'il n'a pas demandé.
    */
    'waves' => [
        'initial_radius_m' => (int) env('DISPATCH_INITIAL_RADIUS_M', 5000),
        'step_m' => (int) env('DISPATCH_RADIUS_STEP_M', 5000),
        'max_radius_m' => (int) env('DISPATCH_MAX_RADIUS_M', 20000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Échéance globale de la recherche (secondes)
    |--------------------------------------------------------------------------
    |
    | Au-delà, on cesse de chercher et on PROPOSE une suite : attendre encore,
    | basculer en rendez-vous, ou annuler. Un écran d'attente qui finit sur un
    | constat est un bug produit.
    |
    | Cinq minutes remplacent l'ambiguïté de l'ancienne fenêtre de deux heures,
    | qui ressemblait à de l'instantané sans en être.
    */
    'search_deadline_seconds' => (int) env('DISPATCH_SEARCH_DEADLINE_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Dernière vague : le broadcast
    |--------------------------------------------------------------------------
    |
    | Au rayon maximal, on cesse d'attendre chacun à son tour : la course part au
    | premier qui accepte. C'est le seul moment où plusieurs modales s'ouvrent en
    | même temps, et le verrou pessimiste de l'acceptation est ce qui empêche deux
    | personnes de partir pour la même course.
    |
    | Le nombre est BORNÉ : faire vibrer deux cents téléphones pour une course
    | qu'un seul obtiendra est le plus sûr moyen de faire couper les
    | notifications — et une fois coupées, elles ne reviennent pas.
    */
    'broadcast_max_candidates' => (int) env('DISPATCH_BROADCAST_MAX', 20),

    /*
    |--------------------------------------------------------------------------
    | Profondeur maximale d'escalade
    |--------------------------------------------------------------------------
    |
    | Après combien de refus ou d'expirations consécutifs le planifié cesse-t-il
    | de proposer et bascule-t-il sur l'assignation d'office.
    */
    'max_escalation_depth' => (int) env('DISPATCH_MAX_ESCALATION', 5),

];
