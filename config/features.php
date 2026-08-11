<?php

/**
 * Feature flags configuration.
 *
 * Values:
 *   bool          — true/false kill switch for everyone
 *   array         — advanced rollout:
 *     ['percentage' => 10]              — 10% of users (deterministic by user_id)
 *     ['users' => [1, 2, 3]]            — explicit user IDs only
 *     ['roles' => ['admin', 'internal']] — role-based
 *     ['enabled' => false]              — explicit kill switch (overrides all)
 *
 * Flags default to false when absent (safe off by default).
 */
return [

    // ─────────────────────────────────────────
    // Payments & monetisation
    // ─────────────────────────────────────────
    'surge_pricing' => true,
    'insurance_real' => true,   // switch from Mock to real Hiscox/Wakam provider
    'premium_tiers' => true,

    // ─────────────────────────────────────────
    // Client-facing features
    // ─────────────────────────────────────────
    'ai_photo_quote' => true,   // AI-based photo → quote estimation
    /*
     * E5 — décrire son besoin en texte plutôt que de choisir un secteur puis un métier.
     *
     * SOUS DRAPEAU, et par défaut ACTIF : le repli est déterministe (recherche par mots-clés sur le
     * catalogue), donc l'assistant fonctionne sans clé d'API — moins finement, et en le disant.
     * Le drapeau sert à le couper si l'interprétation dérive, sans déploiement.
     */
    'ai_order_assistant' => true,

    /*
     * E32 — la modération assistée par IA.
     *
     * COUPÉE PAR DÉFAUT, contrairement à l'assistant de commande. La différence tient au risque :
     * une mauvaise interprétation de commande fait choisir le mauvais métier, et se corrige d'un
     * clic ; un faux positif de modération masque le message de quelqu'un au milieu d'une
     * intervention. On l'allume quand on est prêt à relire ce qu'elle signale.
     *
     * ELLE NE BLOQUE JAMAIS SEULE, drapeau levé ou non : elle PROPOSE, l'administrateur DISPOSE.
     */
    'ai_moderation' => false,
    'trip_tracking_v2' => true,
    'chat_v2' => true,
    'loyalty_redemption' => true,
    'provider_badges' => true,
    'booking_favorites' => true,

    // ─────────────────────────────────────────
    // Provider-facing features
    // ─────────────────────────────────────────
    'presence_v2' => true,
    'fleet_management' => true,

    // ─────────────────────────────────────────
    // Mobile
    // ─────────────────────────────────────────
    'client_mobile_v2' => true,   // Vue 3 islands hybrid (POC)

    // ─────────────────────────────────────────
    // Admin / internal
    // ─────────────────────────────────────────
    'kyb_b2b' => true,
    'matching_v2' => true,

];
