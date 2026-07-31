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

    /*
    |---------------------------------------------------------------------------
    | Preuve de présence — croisement avec la position au moment du scan
    |---------------------------------------------------------------------------
    |
    | Le code affiché par le client atteste d'une POSSESSION, pas d'une présence.
    | Photographié puis envoyé par messagerie, ou simplement dicté au téléphone, il
    | se valide depuis n'importe où pendant ses dix minutes de vie. Croiser avec la
    | position du prestataire au moment du scan est ce qui referme cet écart : il
    | faut alors le code ET être sur place.
    |
    | La position doit accompagner la confirmation. Celle de la session ne convient
    | pas : elle vient du dernier relevé, qu'il suffit de cesser d'émettre en
    | partant pour figer sur une valeur flatteuse.
    */

    // Interrupteur général. À false, la position est enregistrée mais ne bloque rien.
    'presence_geo_check' => (bool) env('TRIP_TRACKING_PRESENCE_GEO_CHECK', true),

    /*
     * Rayon accepté autour du lieu de l'intervention (mètres).
     *
     * Volontairement large. L'objet n'est pas de mesurer finement une présence mais
     * de rendre impossible une confirmation À DISTANCE : depuis son domicile, on est
     * à des kilomètres, pas à 300 mètres. Serrer à 50 m refuserait des prestataires
     * légitimes en centre-ville ou en intérieur, où le relevé dérive — et ce
     * refus-là bloque une intervention payée devant la porte du client, une erreur
     * bien plus coûteuse que de tolérer l'immeuble d'en face.
     */
    'presence_max_distance_m' => (int) env('TRIP_TRACKING_PRESENCE_MAX_M', 250),

    /*
     * Exiger une position pour confirmer.
     *
     * À false, une confirmation sans position passe : l'écart reste ouvert, puisqu'il
     * suffit alors d'omettre le champ pour retrouver le comportement d'avant. Ne
     * mettre à false que le temps d'un déploiement, si d'anciennes versions de
     * l'application prestataire circulent encore.
     */
    'presence_require_position' => (bool) env('TRIP_TRACKING_PRESENCE_REQUIRE_POSITION', true),

    /*
     * Plafond de l'élargissement consenti à un relevé imprécis (mètres).
     *
     * Un relevé annonce sa propre précision, et un relevé honnête mais mauvais mérite
     * d'être jugé sur celle-ci plutôt que refusé. Mais cette valeur vient de
     * l'appareil : sans plafond, il suffirait d'annoncer une précision de 50 km pour
     * rouvrir la porte en grand.
     */
    'presence_max_accuracy_allowance_m' => (int) env('TRIP_TRACKING_PRESENCE_MAX_ACCURACY_M', 500),

    /*
     * Refuser une position signalée comme simulée.
     *
     * Android marque les relevés produits par une application de position fictive
     * (`LocationObject.mocked`). C'est un aveu, pas une détection : un appareil
     * root-é ne le signalera pas. Le refuser coûte une ligne et ferme la fraude la
     * plus accessible — celle qui ne demande qu'une application gratuite.
     */
    'presence_reject_mocked' => (bool) env('TRIP_TRACKING_PRESENCE_REJECT_MOCKED', true),
];
