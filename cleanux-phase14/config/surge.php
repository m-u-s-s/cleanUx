<?php

/**
 * Phase 14 — Configuration du surge pricing.
 *
 * Tous les paramètres sont ajustables sans toucher au code.
 * Override possibles via .env si on veut différents environnements.
 */

return [

    // Cap absolu - aucun surge ne dépasse ce multiplier
    // Belgique/France conseillé : 3.0 max (au-delà, risque "prix abusif")
    'max_multiplier' => env('SURGE_MAX_MULTIPLIER', 3.0),

    // Durée pendant laquelle un surge calculé reste valide
    // Au-delà, on revient à 1.0 (decay naturel)
    'state_ttl_seconds' => env('SURGE_TTL_SECONDS', 600), // 10 min

    // Périodicité de recalcul par RecomputeSurgeJob
    'recompute_every_seconds' => env('SURGE_RECOMPUTE_SECONDS', 60),

    // ──────────────────────────────────────────────────
    // Pondérations des facteurs
    // ──────────────────────────────────────────────────

    'demand' => [
        // Bookings ouverts dans la zone dans la dernière heure
        // multiplier = 1 + max(0, (count - threshold) * weight)
        'lookback_minutes' => 60,
        'threshold'        => 5,    // pas de surge demand sous 5 bookings
        'weight'           => 0.05, // +5% par booking au-dessus du seuil
        'cap'              => 1.5,  // demand seul ne dépasse pas 1.5
    ],

    'supply' => [
        // Inverse : moins il y a de prestataires online, plus ça monte
        // multiplier = 1 + max(0, (threshold - count) * weight)
        'threshold' => 3,
        'weight'    => 0.15, // +15% par "trou"
        'cap'       => 1.6,
    ],

    'temporal' => [
        // Pics horaires (heure locale Europe/Brussels)
        // Format : 'HH-HH' => multiplier
        'peaks' => [
            '07-09' => 1.20,  // matin (départ travail)
            '11-13' => 1.15,  // midi
            '17-19' => 1.30,  // soir (sortie travail)
            '22-02' => 1.20,  // soirée
        ],
        'weekend_extra' => 0.10, // +10% le week-end (en plus des peaks)
    ],

    // ──────────────────────────────────────────────────
    // ASAP — surge supplémentaire pour mode immédiat
    // ──────────────────────────────────────────────────

    'asap_extra_multiplier' => 1.25, // ASAP = ×1.25 sur prix calculé

    // ──────────────────────────────────────────────────
    // UI / Visibilité client
    // ──────────────────────────────────────────────────

    // Si multiplier >= ce seuil, on prévient le client "Tarifs élevés"
    'visible_threshold' => 1.20, // dès +20% on affiche un avertissement

    // ──────────────────────────────────────────────────
    // Service par défaut (durée slot moyen pour estimation)
    // ──────────────────────────────────────────────────

    'default_slot_duration_minutes' => 90,

];
