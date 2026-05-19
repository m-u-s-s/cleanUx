<?php

return [
    'enabled' => env('GEOLOCATION_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Provider actif (résolu via GeolocationV2ServiceProvider)
    |--------------------------------------------------------------------------
    | mock   : données canned (CI, dev local, fallback)
    | google : Google Maps Places + Geocoding + Distance Matrix
    | mapbox : Mapbox Geocoding + Directions Matrix
    */
    'provider' => env('GEO_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            // pas de config
        ],
        'google' => [
            'api_key' => env('GOOGLE_MAPS_API_KEY'),
            'places_endpoint' => 'https://maps.googleapis.com/maps/api/place/autocomplete/json',
            'geocode_endpoint' => 'https://maps.googleapis.com/maps/api/geocode/json',
            'distance_endpoint' => 'https://maps.googleapis.com/maps/api/distancematrix/json',
            'language' => env('GOOGLE_MAPS_LANGUAGE', 'fr'),
        ],
        'mapbox' => [
            'access_token' => env('MAPBOX_ACCESS_TOKEN'),
            'geocode_endpoint' => 'https://api.mapbox.com/geocoding/v5/mapbox.places',
            'directions_endpoint' => 'https://api.mapbox.com/directions-matrix/v1/mapbox',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelist countries (autocomplete restreint à ces codes ISO)
    |--------------------------------------------------------------------------
    */
    'allowed_country_codes' => ['BE', 'FR', 'NL', 'LU', 'DE', 'IT', 'ES', 'CH', 'AT', 'PT'],
    'default_country_code' => env('GEO_DEFAULT_COUNTRY', 'BE'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (minutes). Au-delà → refresh provider call.
    |--------------------------------------------------------------------------
    */
    'cache_ttl_minutes' => (int) env('GEO_CACHE_TTL_MIN', 1440),  // 24h
    'distance_cache_ttl_minutes' => (int) env('GEO_DISTANCE_CACHE_TTL_MIN', 360),  // 6h
    'autocomplete_cache_ttl_minutes' => (int) env('GEO_AUTOCOMPLETE_CACHE_TTL_MIN', 60),  // 1h

    /*
    |--------------------------------------------------------------------------
    | Autocomplete
    |--------------------------------------------------------------------------
    */
    'autocomplete_min_chars' => 3,
    'autocomplete_max_results' => 8,

    /*
    |--------------------------------------------------------------------------
    | Distance modes
    |--------------------------------------------------------------------------
    */
    'distance_modes' => ['driving', 'walking', 'bicycling', 'transit'],
    'distance_default_mode' => 'driving',

    /*
    |--------------------------------------------------------------------------
    | Haversine fallback
    |--------------------------------------------------------------------------
    | Si provider échoue (timeout, quota), on calcule haversine (à vol d'oiseau)
    | et on flag is_fallback_haversine=true sur la row distance_calculations.
    */
    'haversine_fallback_enabled' => true,
    'earth_radius_meters' => 6_371_000,

    /*
    |--------------------------------------------------------------------------
    | Isochrones approximatives (km/h moyens par mode)
    |--------------------------------------------------------------------------
    | Utilisé pour reverse "minutes → rayon en mètres" avant filtrage.
    | Pas un vrai isochrone routing, mais un cercle conservateur.
    */
    'isochrone_avg_speed_kmh' => [
        'driving' => 35,
        'walking' => 4.5,
        'bicycling' => 16,
        'transit' => 22,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP defaults
    |--------------------------------------------------------------------------
    */
    'timeout_seconds' => (int) env('GEO_TIMEOUT_SECONDS', 8),
    'connect_timeout_seconds' => (int) env('GEO_CONNECT_TIMEOUT_SECONDS', 3),
];
