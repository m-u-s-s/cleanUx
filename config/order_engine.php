<?php

return [
    /*
    |---------------------------------------------------------------------------
    | Majoration du service immédiat
    |---------------------------------------------------------------------------
    |
    | Annoncée AVANT confirmation, jamais découverte après. Un client qui apprend
    | la majoration au moment de payer ne revient pas.
    */
    'asap_multiplier' => (float) env('ORDER_ENGINE_ASAP_MULTIPLIER', 1.30),

    /*
    |---------------------------------------------------------------------------
    | Remise multi-services
    |---------------------------------------------------------------------------
    |
    | Paliers par nombre de métiers commandés ensemble. Visible sur le devis
    | (« −8 % en commandant 3 services ensemble ») : une remise que le client ne
    | voit pas ne le décide à rien.
    |
    | Clé = nombre de lignes à partir duquel le palier s'applique.
    */
    'bundle_discount_percent' => [
        2 => 5,
        3 => 8,
        4 => 12,
    ],

    /*
    |---------------------------------------------------------------------------
    | Fourchette d'estimation
    |---------------------------------------------------------------------------
    |
    | La fourchette n'est PAS un pourcentage arbitraire. Elle est calculée sur
    | l'écart réel des réponses possibles : « je ne sais pas » sur « murs seuls
    | ou murs + plafonds » borne le prix entre l'option la moins chère et la plus
    | chère. Le client voit donc ce qu'il risque vraiment, pas une marge inventée.
    |
    | Cet écart-ci ne sert QUE de repli, quand une question inconnue n'offre
    | aucune borne exploitable — ni options, ni min/max de validation.
    */
    'unknown_fallback_spread_percent' => (int) env('ORDER_ENGINE_UNKNOWN_SPREAD', 20),

    /*
     * Élargissement appliqué à toute estimation en mode immédiat : le questionnaire
     * y est volontairement raccourci, donc l'estimation est moins précise. Le dire
     * par une fourchette plus large vaut mieux que d'annoncer une fausse précision.
     */
    'asap_spread_percent' => (int) env('ORDER_ENGINE_ASAP_SPREAD', 15),

    /*
    |---------------------------------------------------------------------------
    | Garde-fous du constructeur de parcours
    |---------------------------------------------------------------------------
    |
    | Au-delà, le back-office avertit l'administrateur. Ce ne sont pas des limites
    | techniques mais des seuils de conversion : un parcours trop long fait perdre
    | des clients, et personne ne s'en aperçoit sans un avertissement explicite.
    */
    'max_questions_per_step' => (int) env('ORDER_ENGINE_MAX_QUESTIONS_STEP', 7),
    'max_questions_warning' => (int) env('ORDER_ENGINE_MAX_QUESTIONS_WARN', 10),

    /*
    |---------------------------------------------------------------------------
    | Preuve de disponibilité
    |---------------------------------------------------------------------------
    |
    | « 14 peintres à moins de 8 km, premier créneau aujourd'hui à 16 h. » La
    | confiance vient de la disponibilité visible, pas d'un badge décoratif — mais
    | seulement si le chiffre est vrai. Un compte gonflé se retourne contre la
    | marque au premier client qui attend.
    */
    'availability_radius_m' => (int) env('ORDER_ENGINE_AVAILABILITY_RADIUS_M', 8000),

    // Rayon proposé quand le premier ne donne rien : une impasse doit offrir une suite.
    'availability_wider_radius_m' => (int) env('ORDER_ENGINE_AVAILABILITY_WIDER_M', 25000),

    /*
     * Nombre d'agendas consultés pour trouver le premier créneau.
     *
     * La disponibilité se calcule prestataire par prestataire, et cet appel se
     * déclenche à la saisie de l'adresse. Consulter cinquante agendas pour afficher
     * une phrase rassurante ferait ramer l'écran qu'elle est censée servir.
     */
    'availability_sample_size' => (int) env('ORDER_ENGINE_AVAILABILITY_SAMPLE', 5),

    'availability_horizon_days' => (int) env('ORDER_ENGINE_AVAILABILITY_HORIZON_DAYS', 7),
];
