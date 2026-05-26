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
    'surge_pricing'       => true,
    'insurance_real'      => true,   // switch from Mock to real Hiscox/Wakam provider
    'premium_tiers'       => true,

    // ─────────────────────────────────────────
    // Client-facing features
    // ─────────────────────────────────────────
    'ai_photo_quote'      => true,   // AI-based photo → quote estimation
    'trip_tracking_v2'    => true,
    'chat_v2'             => true,
    'loyalty_redemption'  => true,
    'provider_badges'     => true,
    'booking_favorites'   => true,

    // ─────────────────────────────────────────
    // Provider-facing features
    // ─────────────────────────────────────────
    'presence_v2'         => true,
    'fleet_management'    => false,

    // ─────────────────────────────────────────
    // Mobile
    // ─────────────────────────────────────────
    'client_mobile_v2'    => true,   // Vue 3 islands hybrid (POC)

    // ─────────────────────────────────────────
    // Admin / internal
    // ─────────────────────────────────────────
    'kyb_b2b'             => true,
    'matching_v2'         => true,

];
