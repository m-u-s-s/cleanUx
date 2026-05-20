<?php

return [
    'enabled' => env('PRESENCE_ENABLED', true),

    // Auto-mark offline si pas de heartbeat depuis N minutes
    'stale_after_minutes' => (int) env('PRESENCE_STALE_MIN', 5),

    // Intervalle heartbeat recommandé pour client mobile/web (secondes)
    'heartbeat_interval_seconds' => (int) env('PRESENCE_HEARTBEAT_SEC', 60),

    // Auto-busy quand provider accept une mission (via observer)
    'auto_busy_on_accept' => env('PRESENCE_AUTO_BUSY', true),

    // Auto-online quand provider termine une mission (au lieu de rester busy)
    'auto_online_on_mission_complete' => env('PRESENCE_AUTO_ONLINE_AFTER_MISSION', true),
];
