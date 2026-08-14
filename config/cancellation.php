<?php

/**
 * Phase 14 — Politique d'annulation et frais.
 *
 * Inspiré du modèle Uber : free cancellation jusqu'à X minutes avant le RDV
 * (ou X minutes après confirmation pour ASAP), puis frais progressifs.
 *
 * Toutes les valeurs en pourcentage du prix du booking, sauf précisé.
 */

return [

    // ──────────────────────────────────────────────
    // Annulation par le CLIENT
    // ──────────────────────────────────────────────

    'client' => [
        // Fenêtre de grâce gratuite après création (ASAP) ou avant RDV (scheduled)
        'free_cancellation_minutes' => 5,

        // Tiers de frais (pourcentage du prix selon le délai avant RDV)
        // Le 1er tier qui matche est appliqué.
        'fee_tiers' => [
            // Plus de 24h avant : gratuit
            ['min_hours_before' => 24, 'fee_percent' => 0],
            // Entre 2h et 24h : 25%
            ['min_hours_before' => 2,  'fee_percent' => 25],
            // Entre 30 min et 2h : 50%
            ['min_minutes_before' => 30, 'fee_percent' => 50],
            // Moins de 30 min ou après le start : 100%
            ['min_minutes_before' => 0,  'fee_percent' => 100],
        ],

        // Fee fixe minimum en euros (même si pourcentage = 0)
        // Ex : 0.50 € pour couvrir frais Stripe
        'minimum_fee_eur' => 0.00,
    ],

    // ──────────────────────────────────────────────
    // Annulation par le PRESTATAIRE
    // ──────────────────────────────────────────────

    'provider' => [
        // Annulation par le prestataire = pénalité (impacte sa fiabilité)
        // car ça oblige le client à attendre + redispatch
        'penalty_eur' => 5.00,    // pénalité fixe par annulation
        'reliability_penalty' => 10,       // points retirés du score reliability
        'free_cancellation_minutes' => 30,    // Plus de X min avant : pas de pénalité

        // Au-delà de N annulations / 30 jours, déclencher review admin
        'max_cancellations_per_30d' => 5,
    ],

    // ──────────────────────────────────────────────
    // No-show (le prestataire ou le client ne se présente pas)
    // ──────────────────────────────────────────────

    'no_show' => [
        // Délai après planned_start_at où on considère un no-show
        'grace_minutes' => 15,

        /*
         * L'ATTENTE AU POINT DE PRISE EN CHARGE, sur une course.
         *
         * Cinq minutes, comme chez Uber, Bolt et Heetch dans la plupart des villes. Le repère n'est
         * PAS l'horaire prévu — une course immédiate n'en a pas — mais le moment où le conducteur
         * signale son arrivée. C'est de là que le passager en retard vit le décompte comme
         * équitable, et c'est le seul instant horodaté dont on dispose.
         *
         * Quinze minutes conviennent à un ménage : la personne est chez elle et cherche ses clés.
         * Les appliquer à une voiture arrêtée en double file, moteur tournant, reviendrait à faire
         * travailler le conducteur gratuitement pendant un quart d'heure.
         */
        'ride_grace_minutes' => (int) env('CANCELLATION_RIDE_GRACE_MINUTES', 5),

        // Si client no-show : fee = 100% du prix
        'client_fee_percent' => 100,

        // Si provider no-show : pénalité forte
        'provider_penalty_eur' => 20.00,
        'provider_reliability_penalty' => 30,
    ],

];
