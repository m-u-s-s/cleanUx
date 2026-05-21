<?php

return [
    'enabled' => env('MASKED_CALLS_ENABLED', false),

    // Twilio Proxy : ServiceSid créé sur Twilio Console > Proxy > Services
    'twilio_service_sid' => env('TWILIO_PROXY_SERVICE_SID'),

    // Durée par défaut session (heures)
    'session_ttl_hours' => (int) env('MASKED_CALLS_TTL_HOURS', 24),

    // Auto-open sur booking creation si activé
    'auto_open_on_booking' => (bool) env('MASKED_CALLS_AUTO_OPEN', true),

    // Auto-close N heures après mission completion
    'auto_close_after_completion_hours' => (int) env('MASKED_CALLS_AUTO_CLOSE_HOURS', 24),
];
