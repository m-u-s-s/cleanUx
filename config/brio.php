<?php

return [
    'platform_fee_percent' => (int) env('BRIO_PLATFORM_FEE_PERCENT', 15),

    // Décision produit 2026-06-11 : commission = TAUX UNIQUE au lancement.
    // L'infra de taux négocié par prestataire (ProviderProfile.commission_rate) reste
    // en base mais n'est appliquée que si ce flag est activé. Tant qu'il est off,
    // tout le monde est facturé à platform_fee_percent — et le calcul reste aligné
    // sur le montant réellement prélevé par Stripe (MissionPaymentService).
    'use_negotiated_commission' => (bool) env('BRIO_USE_NEGOTIATED_COMMISSION', false),

    'booking' => [
        'default_duration_minutes' => 90,
        'default_buffer_minutes' => 30,
    ],

    'notifications' => [
        'prune_after_days' => 30,
    ],

    'security' => [
        'require_active_account' => true,
    ],

    'seed' => [
        /*
         * LE MOT DE PASSE DE TOUS LES COMPTES SEMÉS — un seul, pour pouvoir se connecter partout
         * pendant une vérification manuelle.
         *
         * Chaque seeder posait le sien : « password » ici, « demo2026! » là, « QaPhase2! » ailleurs.
         * Vérifier un parcours à travers les rôles demandait donc de retrouver dans quel fichier
         * l'compte avait été créé — et le premier réflexe, en cas de doute, était de réinitialiser
         * la base.
         *
         * ENV-SURCHARGEABLE, ET C'EST LE POINT. Le défaut sert un poste de travail ; un hébergement
         * de démonstration accessible depuis l'extérieur pose `BRIO_SEED_PASSWORD` et n'a rien
         * d'autre à changer. Sans ce levier, la valeur de confort serait aussi la valeur déployée.
         */
        'password' => env('BRIO_SEED_PASSWORD', '12345678'),
        'profile' => env('BRIO_SEED_PROFILE'),
        'default_profile' => env('BRIO_SEED_DEFAULT_PROFILE', env('APP_ENV') === 'production' ? 'production' : 'demo'),
        'allowed_profiles' => ['demo', 'reference', 'production'],
    ],
];
