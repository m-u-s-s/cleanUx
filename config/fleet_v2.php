<?php

return [
    'enabled' => env('FLEET_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Types autorisés
    |--------------------------------------------------------------------------
    */
    'vehicle_types' => ['van', 'truck', 'car', 'scooter', 'trailer', 'other'],
    'fuel_types' => ['diesel', 'petrol', 'electric', 'hybrid', 'gnv', 'other'],
    'equipment_types' => ['tool', 'machine', 'consumable', 'protection'],
    'equipment_categories' => [
        'cleaning', 'painting', 'plumbing', 'electrical', 'roofing',
        'gardening', 'carpentry', 'misc',
    ],
    'maintenance_types' => ['preventive', 'corrective', 'inspection'],
    'certification_types' => [
        'insurance', 'control_technique',
        'driver_license', 'professional_qualification',
        'asbestos_training', 'height_work_authorization',
        'first_aid', 'electric_clearance', 'other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Alertes expiration
    |--------------------------------------------------------------------------
    | Délai avant expiration pour passer en status=expiring_soon
    */
    'expiring_soon_days' => (int) env('FLEET_EXPIRING_SOON_DAYS', 30),
    'block_assignment_on_expired_cert' => env('FLEET_BLOCK_ON_EXPIRED_CERT', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-update du statut véhicule selon assignment (event-driven)
    |--------------------------------------------------------------------------
    */
    'auto_update_status_on_assign' => true,

    /*
    |--------------------------------------------------------------------------
    | Maintenance preventive — intervalle par défaut entre 2 entretiens
    |--------------------------------------------------------------------------
    */
    'default_maintenance_interval_days' => [
        'van' => 365,
        'truck' => 180,
        'car' => 365,
        'scooter' => 180,
        'trailer' => 730,
        'other' => 365,
    ],
    'default_maintenance_interval_km' => [
        'van' => 20_000,
        'truck' => 15_000,
        'car' => 20_000,
        'scooter' => 10_000,
        'other' => 20_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Document storage
    |--------------------------------------------------------------------------
    */
    'document_storage_disk' => env('FLEET_DOC_DISK', 'local'),
    'document_path_prefix' => env('FLEET_DOC_PATH', 'fleet_documents'),

    /*
    |--------------------------------------------------------------------------
    | Damage workflow
    |--------------------------------------------------------------------------
    */
    'damage_conditions' => ['ok', 'damaged', 'lost', 'needs_maintenance'],
    'auto_schedule_maintenance_on_damage' => true,

    /*
    |--------------------------------------------------------------------------
    | Règles du transport de personnes
    |--------------------------------------------------------------------------
    |
    | S'appliquent aux seuls métiers dont l'administrateur a coché « règles taxi »
    | (`trades.taxi_rules`). C'est la règle que posent Uber, Bolt et Heetch dans la plupart des
    | villes : un véhicule trop ancien n'est pas accepté en transport de personnes.
    |
    | L'âge est CALCULÉ depuis la date de première immatriculation déclarée par le prestataire et
    | attestée par sa carte grise — jamais saisi, et jamais jugé à l'œil sur une photo. Un véhicule
    | vieillit : le contrôle doit donc pouvoir être rejoué, pas seulement passé une fois.
    |
    | Le plafond varie d'une ville à l'autre chez tous les concurrents ; `by_country` permet de le
    | dire sans déploiement, et l'absence de clé retombe sur la valeur générale.
    */
    'taxi_rules' => [
        'max_vehicle_age_years' => (int) env('FLEET_TAXI_MAX_VEHICLE_AGE_YEARS', 4),
        'by_country' => [
            // 'BE' => 5,
        ],
    ],
];
