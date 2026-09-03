<?php

/*
 * LA LOCATION ENTRE MEMBRES.
 *
 * Un module a part de « Nos locations » : ici la plateforme ne loue rien, elle met deux
 * membres en relation, encaisse et reverse. Les reglages qui suivent ne concernent QUE lui.
 */
return [

    /*
     * LA COMMISSION.
     *
     * Elle n'est pas celle des prestations : la location immobilise un bien et porte un risque
     * de dommage, que la plateforme arbitre. Les places de marche du secteur se situent entre
     * 25 % et 35 % ; 25 % est le point de depart, reglable sans deploiement.
     */
    'commission_percent' => (int) env('PEER_RENTAL_COMMISSION_PERCENT', 25),

    /*
     * UN TAUX PROPRE A UN TYPE DE BIEN, quand on le veut.
     *
     * Le risque d'un logement n'est pas celui d'une voiture : les places de marche du secteur
     * l'ont toutes tranche ainsi. Une entree absente laisse le taux general s'appliquer — aucune
     * decision n'est donc forcee, et le taux reste FIGE sur chaque location deja conclue.
     */
    'commission_percent_par_type' => [
        'vehicle' => env('PEER_RENTAL_COMMISSION_VEHICLE_PERCENT'),
        'stay' => env('PEER_RENTAL_COMMISSION_STAY_PERCENT'),
    ],

    /*
     * UNE AUTORISATION STRIPE EXPIRE AU BOUT DE SEPT JOURS.
     *
     * Une reservation prise trois semaines a l'avance verrait ses fonds retomber avant la
     * remise. `reauthorize_hours_before` declenche une nouvelle empreinte avant l'echeance ;
     * `authorization_days` dit la duree que Stripe nous accorde.
     */
    'authorization_days' => (int) env('PEER_RENTAL_AUTHORIZATION_DAYS', 7),
    'reauthorize_hours_before' => (int) env('PEER_RENTAL_REAUTHORIZE_HOURS_BEFORE', 24),

    /* Le proprietaire a ce delai pour accepter ; passe lui, les fonds sont rendus. */
    'owner_response_hours' => (int) env('PEER_RENTAL_OWNER_RESPONSE_HOURS', 24),

    /* Le code de remise et de retour : six chiffres, valables une demi-journee. */
    'code_ttl_hours' => (int) env('PEER_RENTAL_CODE_TTL_HOURS', 12),

    /*
     * LES BAREMES D'ANNULATION, DU POINT DE VUE DU LOCATAIRE.
     *
     * `heures` est le delai AVANT le debut de la location ; `retenue_percent` la part du
     * loyer conservee. Au-dela du dernier palier, remboursement integral.
     */
    'cancellation' => [
        'souple' => [
            ['heures' => 24, 'retenue_percent' => 0],
            ['heures' => 0, 'retenue_percent' => 50],
        ],
        'moderee' => [
            ['heures' => 72, 'retenue_percent' => 0],
            ['heures' => 24, 'retenue_percent' => 50],
            ['heures' => 0, 'retenue_percent' => 100],
        ],
        'stricte' => [
            ['heures' => 168, 'retenue_percent' => 0],
            ['heures' => 0, 'retenue_percent' => 100],
        ],
    ],

    /* Le proprietaire qui se desiste apres avoir accepte : penalite au profit du locataire. */
    'owner_withdrawal_penalty_percent' => (int) env('PEER_RENTAL_OWNER_PENALTY_PERCENT', 20),

    /*
     * LA TARIFICATION DYNAMIQUE — multiplicateurs par defaut, surchargeables par annonce
     * dans `peer_vehicles.pricing_rules`.
     */
    'pricing' => [
        'weekend_multiplier' => (float) env('PEER_RENTAL_WEEKEND_MULTIPLIER', 1.15),
        'high_season_multiplier' => (float) env('PEER_RENTAL_HIGH_SEASON_MULTIPLIER', 1.20),
        // Mois de haute saison, du 1er au dernier jour.
        'high_season_months' => [7, 8, 12],
    ],

    /* Le kilometrage supplementaire se facture au retour, sur la caution. */
    'late_return_fee_per_hour_cents' => (int) env('PEER_RENTAL_LATE_FEE_CENTS', 1500),

    /* La difference de carburant, en centimes par huitieme de reservoir manquant. */
    'fuel_missing_eighth_cents' => (int) env('PEER_RENTAL_FUEL_EIGHTH_CENTS', 1200),

    /*
     * LES COQUILLES BRANCHABLES.
     *
     * Ni l'assurance partenaire ni la telematique n'ont de contrat : leurs adaptateurs de
     * demonstration repondent, mais rien n'est souscrit ni deverrouille pour de vrai. Passer
     * a `true` sans partenaire promettrait au locataire une couverture qui n'existe pas.
     */
    'insurance' => [
        'enabled' => (bool) env('PEER_RENTAL_INSURANCE_ENABLED', false),
        'driver' => env('PEER_RENTAL_INSURANCE_DRIVER', 'demo'),
        'plans' => [
            'basique' => ['label' => 'Basique', 'daily_cents' => 0, 'franchise_cents' => 100000],
            'confort' => ['label' => 'Confort', 'daily_cents' => 900, 'franchise_cents' => 40000],
            'serenite' => ['label' => 'Sérénité', 'daily_cents' => 1800, 'franchise_cents' => 0],
        ],
    ],

    'telematics' => [
        'enabled' => (bool) env('PEER_RENTAL_TELEMATICS_ENABLED', false),
        'driver' => env('PEER_RENTAL_TELEMATICS_DRIVER', 'demo'),
    ],
];
