<?php

/**
 * Configuration du workflow Disputes / SAV (CleanUx).
 *
 * `sla_hours` : délai cible de première réponse selon priority × severity.
 * `escalation_hours` : à partir de combien d'heures sans résolution on escalade
 *                       vers le niveau suivant.
 */

return [
    'sla_hours' => [
        'urgent' => 4,
        'high' => 12,
        'normal' => 24,
        'low' => 48,
    ],

    'escalation_hours' => [
        // Niveau 1 → 2 (Tier-1 → Tier-2 senior support)
        1 => 24,
        // Niveau 2 → 3 (Tier-2 → manager)
        2 => 48,
    ],

    'max_escalation_level' => 3,

    'auto_resolution' => [
        'enabled' => env('DISPUTES_AUTO_RESOLUTION', true),

        // Catégories éligibles à l'auto-résolution si conditions remplies
        'auto_refund_categories' => [
            'no_show',          // Provider absent
            'payment',          // Paiement erroné
        ],

        // Si une catégorie déclenche un refund auto, plafond montant
        'auto_refund_max_amount' => (float) env('DISPUTES_AUTO_REFUND_MAX', 200),
    ],

    'reference_prefix' => env('DISPUTES_REF_PREFIX', 'DSP'),
];
