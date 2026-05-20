<?php

return [
    'enabled' => env('TRIP_TRACKING_ENABLED', true),

    // Rayon geofence pour auto-transition enroute → arrived (mètres)
    'geofence_radius_m' => (int) env('TRIP_TRACKING_GEOFENCE_M', 150),

    // Vitesse urbaine par défaut pour ETA fallback (mètres/sec) — 11.11 mps = 40 km/h
    'default_speed_mps' => (float) env('TRIP_TRACKING_DEFAULT_SPEED_MPS', 11.11),

    // Auto-cancel session si pas de ping pendant N minutes
    'stale_after_minutes' => (int) env('TRIP_TRACKING_STALE_MIN', 30),

    // Retention des points GPS individuels (jours) — sessions gardées indéfiniment
    'points_retention_days' => (int) env('TRIP_TRACKING_POINTS_RETENTION_DAYS', 90),
];
