<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interrupteur général
    |--------------------------------------------------------------------------
    |
    | Coupe le module en entier, quelle que soit la configuration des métiers et
    | des zones. C'est le cran d'arrêt d'urgence, pas le réglage de tous les jours :
    | l'activation fine se pilote depuis /admin/modules (audience par zone) et
    | depuis la fiche métier (case « contrôle facial »).
    |
    */

    'enabled' => env('FACE_CHECK_ENABLED', true),

    /*
    | La clé du module dans `platform_modules`. C'est elle qui porte l'audience
    | par zone, résolue par PlatformModuleResolver.
    */
    'module_key' => 'security.face_check',

    /*
    |--------------------------------------------------------------------------
    | Fournisseur de comparaison faciale
    |--------------------------------------------------------------------------
    |
    | `mock` est DÉTERMINISTE : le même visage rend toujours le même score. C'est
    | ce qui rend les tests stables, et c'est aussi ce qui permet de faire tourner
    | la plateforme entière sans clé ni facture.
    |
    | `onfido` réutilise la clé du module KYC — il n'y a qu'un seul compte Onfido.
    |
    */

    'provider' => env('FACE_CHECK_PROVIDER', 'mock'),

    'disk' => env('FACE_CHECK_DISK', 'private'),

    /*
    |--------------------------------------------------------------------------
    | Cadence
    |--------------------------------------------------------------------------
    |
    | AU PLUS un contrôle par `min_hours`, AU MOINS un par `max_hours`. Le moment
    | exact est tiré au sort dans cette fenêtre, côté serveur, au moment du
    | contrôle précédent — et n'est jamais renvoyé au client.
    |
    | 24 h / 72 h : un prestataire actif tous les jours voit un contrôle tous les
    | un à trois jours, sans jamais pouvoir prévoir lequel.
    |
    */

    'interval' => [
        'min_hours' => (int) env('FACE_CHECK_MIN_HOURS', 24),
        'max_hours' => (int) env('FACE_CHECK_MAX_HOURS', 72),
    ],

    /*
    | Durée de vie d'un contrôle ouvert. Passé ce délai sans réponse, il devient
    | `expired` — et un `expired` ne compte PAS comme un abandon : le prestataire
    | n'a peut-être jamais vu l'écran.
    */
    'check_ttl_minutes' => (int) env('FACE_CHECK_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Décision
    |--------------------------------------------------------------------------
    */

    // Score de similarité minimal, sur 100, pour qu'un contrôle passe tout seul.
    'match_threshold' => (float) env('FACE_CHECK_MATCH_THRESHOLD', 75.0),

    // Seuil d'appariement avec la pièce d'identité — plus tolérant : une photo de
    // carte scannée est de bien moins bonne qualité qu'un selfie.
    'id_match_threshold' => (float) env('FACE_CHECK_ID_MATCH_THRESHOLD', 65.0),

    /*
    | La vivacité n'est pas une option. Sans elle, une photo d'une photo passe et
    | le module ne prouve strictement rien — c'est le premier reproche fait aux
    | plateformes qui se contentent d'une comparaison d'images.
    */
    'liveness_required' => env('FACE_CHECK_LIVENESS_REQUIRED', true),

    // Nombre de tentatives dans un même contrôle avant blocage dur.
    'max_attempts' => (int) env('FACE_CHECK_MAX_ATTEMPTS', 3),

    // Échecs consécutifs (contrôles distincts) avant blocage dur.
    'failure_threshold' => (int) env('FACE_CHECK_FAILURE_THRESHOLD', 3),

    /*
    |--------------------------------------------------------------------------
    | Alertes
    |--------------------------------------------------------------------------
    |
    | On n'alerte PAS au premier abandon. Un réseau qui lâche, une batterie vide,
    | un appel entrant produisent exactement le même état. C'est la répétition qui
    | devient un signal.
    |
    */

    'abandon' => [
        'threshold' => (int) env('FACE_CHECK_ABANDON_THRESHOLD', 3),
        'window_days' => (int) env('FACE_CHECK_ABANDON_WINDOW_DAYS', 7),
        // Au-delà de ce nombre d'abandons dans la fenêtre, l'incident passe en
        // `critical` : ce n'est plus une panne, c'est un évitement.
        'fraud_threshold' => (int) env('FACE_CHECK_ABANDON_FRAUD_THRESHOLD', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enrôlement
    |--------------------------------------------------------------------------
    |
    | `grace_days = 0` DÉLIBÉRÉMENT. Une période de grâce a du sens pour une pièce
    | qu'il faut aller chercher (un permis, une assurance) : elle n'en a aucun pour
    | un selfie qui prend trente secondes. Une grâce sur l'enrôlement, c'est une
    | protection qui n'existe pas pendant N jours. Le réglage reste ouvert pour un
    | déploiement progressif, mais le défaut protège.
    |
    */

    'enrolment_grace_days' => (int) env('FACE_CHECK_ENROLMENT_GRACE_DAYS', 0),

    // Version du texte de consentement. À incrémenter dès que le texte change :
    // un consentement donné sur une ancienne version n'en couvre pas une nouvelle.
    'consent_version' => env('FACE_CHECK_CONSENT_VERSION', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | RGPD — conservation
    |--------------------------------------------------------------------------
    |
    | Le visage de référence vit tant que le compte vit. Les selfies de CONTRÔLE,
    | eux, sont éphémères : passé ce délai, le fichier est effacé du disque et seuls
    | le verdict et le score subsistent. C'est la minimisation de l'article 5.1.c
    | appliquée à une donnée de l'article 9.
    |
    */

    'selfie_retention_days' => (int) env('FACE_CHECK_SELFIE_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Onfido
    |--------------------------------------------------------------------------
    |
    | Une seule clé pour les deux modules : il n'y a qu'un compte Onfido, et le
    | prestataire n'y a qu'un seul « applicant ».
    |
    */

    'onfido' => [
        'api_token' => env('ONFIDO_API_TOKEN'),
        'base_url' => env('ONFIDO_BASE_URL', 'https://api.eu.onfido.com/v3.6'),
        'timeout' => (int) env('FACE_CHECK_HTTP_TIMEOUT', 30),
    ],

];
