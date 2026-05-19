<?php

namespace Database\Seeders;

use App\Models\Trade;
use Illuminate\Database\Seeder;

/**
 * Phase 1 — Seed des corps de métier (Trades) de référence.
 *
 * Idempotent : utilise updateOrCreate par slug.
 * À ajouter dans DatabaseSeeder.php (ou ton profil de seed) :
 *   $this->call(TradeSeeder::class);
 *   $this->call(ServiceCatalogTradeBackfillSeeder::class);
 */
class TradeSeeder extends Seeder
{
    public function run(): void
    {
        $trades = [
            [
                'slug'                    => 'nettoyage',
                'code'                    => 'CLEANING',
                'name'                    => 'Nettoyage',
                'icon'                    => 'broom',
                'color'                   => '#0EA5E9',
                'short_description'       => 'Nettoyage récurrent ou ponctuel pour particuliers et entreprises.',
                'description'             => 'Tous types de prestations de nettoyage : domestique, bureaux, vitrerie, fin de chantier, désinfection.',
                'is_active'               => true,
                'requires_certification'  => false,
                'requires_insurance_proof'=> true,
                'is_b2b_default'          => true,
                'is_personal_default'     => true,
                'sort_order'              => 10,
                'booking_form_schema'     => self::cleaningBookingFormSchema(),
            ],
            [
                'slug'                    => 'batiment',
                'code'                    => 'BUILDING',
                'name'                    => 'Bâtiment',
                'icon'                    => 'hammer',
                'color'                   => '#F59E0B',
                'short_description'       => 'Travaux de construction, rénovation et maçonnerie.',
                'description'             => 'Gros œuvre, second œuvre, isolation, plâtrerie, carrelage, étanchéité.',
                'is_active'               => true,
                'requires_certification'  => true,
                'requires_insurance_proof'=> true,
                'is_b2b_default'          => true,
                'is_personal_default'     => true,
                'sort_order'              => 20,
                'booking_form_schema'     => self::buildingBookingFormSchema(),
            ],
            [
                'slug'                    => 'peinture',
                'code'                    => 'PAINTING',
                'name'                    => 'Peinture',
                'icon'                    => 'paint-brush',
                'color'                   => '#A855F7',
                'short_description'       => 'Peinture intérieure, extérieure et décoration murale.',
                'description'             => 'Peinture, enduits, revêtements muraux, papiers peints, ravalement de façade.',
                'is_active'               => true,
                'requires_certification'  => false,
                'requires_insurance_proof'=> true,
                'is_b2b_default'          => true,
                'is_personal_default'     => true,
                'sort_order'              => 30,
                'booking_form_schema'     => self::paintingBookingFormSchema(),
            ],
            [
                'slug'                    => 'levage',
                'code'                    => 'LIFTING',
                'name'                    => 'Levage / Manutention',
                'icon'                    => 'forklift',
                'color'                   => '#EF4444',
                'short_description'       => 'Engins de levage, nacelles, monte-charges, déménagement lourd.',
                'description'             => 'Location avec opérateur certifié, manutention industrielle, chantiers en hauteur.',
                'is_active'               => true,
                'requires_certification'  => true,    // CACES R486 etc.
                'requires_insurance_proof'=> true,
                'is_b2b_default'          => true,
                'is_personal_default'     => false,
                'sort_order'              => 40,
                'booking_form_schema'     => self::liftingBookingFormSchema(),
            ],
            [
                'slug'                    => 'jardinage',
                'code'                    => 'GARDENING',
                'name'                    => 'Jardinage',
                'icon'                    => 'leaf',
                'color'                   => '#22C55E',
                'short_description'       => 'Entretien, aménagement, élagage et création paysagère.',
                'description'             => 'Tonte, taille, élagage, désherbage, plantation, création de jardins.',
                'is_active'               => true,
                'requires_certification'  => false,
                'requires_insurance_proof'=> false,
                'is_b2b_default'          => true,
                'is_personal_default'     => true,
                'sort_order'              => 50,
                'booking_form_schema'     => self::gardeningBookingFormSchema(),
            ],
        ];

        foreach ($trades as $payload) {
            Trade::updateOrCreate(
                ['slug' => $payload['slug']],
                $payload
            );
        }
    }

    /**
     * Phase F3 — schema dynamique du métier Nettoyage.
     * Mirroring des champs cleaning historiques pour permettre leur retrait
     * du formulaire legacy. Les nouvelles bookings Nettoyage stockent leurs
     * réponses dans bookings.trade_form_answers au lieu des colonnes legacy
     * (surface, frequence, type_lieu, options_prestation, zones_specifiques,
     * presence_animaux, acces_parking, materiel_fournit).
     */
    public static function cleaningBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key'      => 'type_lieu',
                    'label'    => 'Type de lieu',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => [
                        ['value' => 'appartement', 'label' => 'Appartement', 'price_delta' => 0],
                        ['value' => 'maison',      'label' => 'Maison',      'price_delta' => 0],
                        ['value' => 'bureau',      'label' => 'Bureau',      'price_delta' => 0],
                        ['value' => 'commerce',    'label' => 'Commerce',    'price_delta' => 0],
                        ['value' => 'autre',       'label' => 'Autre',       'price_delta' => 0],
                    ],
                ],
                [
                    'key'      => 'surface',
                    'label'    => 'Surface',
                    'type'     => 'select',
                    'required' => true,
                    'unit'     => 'm²',
                    'options'  => [
                        ['value' => 'moins_50',  'label' => 'Moins de 50 m²',  'price_delta' => 0],
                        ['value' => '50_100',    'label' => '50 à 100 m²',     'price_delta' => 20],
                        ['value' => '100_150',   'label' => '100 à 150 m²',    'price_delta' => 45],
                        ['value' => '150_250',   'label' => '150 à 250 m²',    'price_delta' => 80],
                        ['value' => 'plus_250',  'label' => 'Plus de 250 m²',  'price_delta' => 130],
                    ],
                ],
                [
                    'key'      => 'frequence',
                    'label'    => 'Fréquence',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => [
                        ['value' => 'ponctuel',    'label' => 'Ponctuel',     'price_delta' => 0],
                        ['value' => 'hebdo',       'label' => 'Hebdomadaire', 'price_delta' => 0],
                        ['value' => 'bimensuel',   'label' => 'Bimensuel',    'price_delta' => 0],
                        ['value' => 'mensuel',     'label' => 'Mensuel',      'price_delta' => 0],
                    ],
                ],
                [
                    'key'      => 'options_prestation',
                    'label'    => 'Options supplémentaires',
                    'type'     => 'multiselect',
                    'required' => false,
                    'options'  => [
                        ['value' => 'vitres',       'label' => 'Nettoyage des vitres',  'price_delta' => 15],
                        ['value' => 'frigo',        'label' => 'Intérieur frigo',        'price_delta' => 10],
                        ['value' => 'four',         'label' => 'Intérieur four',         'price_delta' => 10],
                        ['value' => 'repassage',    'label' => 'Repassage',              'price_delta' => 20],
                        ['value' => 'desinfection', 'label' => 'Désinfection complète',  'price_delta' => 25],
                    ],
                ],
                [
                    'key'      => 'zones_specifiques',
                    'label'    => 'Zones à nettoyer',
                    'type'     => 'multiselect',
                    'required' => false,
                    'options'  => [
                        ['value' => 'cuisine',         'label' => 'Cuisine',         'price_delta' => 0],
                        ['value' => 'salle_de_bain',   'label' => 'Salle de bain',   'price_delta' => 0],
                        ['value' => 'salon',           'label' => 'Salon',           'price_delta' => 0],
                        ['value' => 'chambres',        'label' => 'Chambres',        'price_delta' => 0],
                        ['value' => 'bureau',          'label' => 'Bureau',          'price_delta' => 0],
                        ['value' => 'escaliers',       'label' => 'Escaliers',       'price_delta' => 0],
                    ],
                ],
                [
                    'key'      => 'presence_animaux',
                    'label'    => 'Animaux présents sur place',
                    'type'     => 'boolean',
                    'default'  => false,
                ],
                [
                    'key'      => 'acces_parking',
                    'label'    => 'Accès parking disponible',
                    'type'     => 'boolean',
                    'default'  => false,
                ],
                [
                    'key'      => 'materiel_fournit',
                    'label'    => 'Je fournis le matériel',
                    'type'     => 'boolean',
                    'default'  => false,
                    'help'     => 'Cochez si vous fournissez vos propres produits / équipements.',
                ],
            ],
        ];
    }

    /**
     * Phase F3 — schema dynamique du métier Bâtiment.
     */
    public static function buildingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key'      => 'type_intervention',
                    'label'    => 'Type d\'intervention',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => [
                        ['value' => 'gros_oeuvre',   'label' => 'Gros œuvre',          'price_delta' => 0],
                        ['value' => 'second_oeuvre', 'label' => 'Second œuvre',        'price_delta' => 0],
                        ['value' => 'renovation',    'label' => 'Rénovation',          'price_delta' => 0],
                        ['value' => 'isolation',     'label' => 'Isolation',           'price_delta' => 0],
                        ['value' => 'etancheite',    'label' => 'Étanchéité',          'price_delta' => 0],
                    ],
                ],
                [
                    'key'      => 'surface',
                    'label'    => 'Surface concernée',
                    'type'     => 'number',
                    'unit'     => 'm²',
                    'required' => true,
                    'min'      => 1,
                    'max'      => 10000,
                    'step'     => 1,
                ],
                [
                    'key'      => 'date_debut_souhaitee',
                    'label'    => 'Date de début souhaitée',
                    'type'     => 'text',
                    'required' => false,
                    'help'     => 'Format libre — ex: « semaine du 12 juin », « après le 1er juillet ».',
                    'max_length' => 120,
                ],
                [
                    'key'      => 'details_techniques',
                    'label'    => 'Détails techniques',
                    'type'     => 'textarea',
                    'required' => false,
                    'max_length' => 2000,
                    'help'     => 'Description de la demande (matériaux, contraintes, accessibilité…).',
                ],
            ],
        ];
    }

    /**
     * Phase F3 — schema dynamique du métier Peinture.
     */
    public static function paintingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key'      => 'type_surface',
                    'label'    => 'Surface à peindre',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => [
                        ['value' => 'murs',       'label' => 'Murs intérieurs',  'price_delta' => 0],
                        ['value' => 'plafonds',   'label' => 'Plafonds',         'price_delta' => 10],
                        ['value' => 'boiseries',  'label' => 'Boiseries',        'price_delta' => 20],
                        ['value' => 'facade',     'label' => 'Façade extérieure','price_delta' => 50],
                        ['value' => 'mix',        'label' => 'Plusieurs zones',  'price_delta' => 30],
                    ],
                ],
                [
                    'key'      => 'surface',
                    'label'    => 'Surface à peindre',
                    'type'     => 'number',
                    'unit'     => 'm²',
                    'required' => true,
                    'min'      => 1,
                    'max'      => 5000,
                    'step'     => 1,
                ],
                [
                    'key'      => 'couleur_souhaitee',
                    'label'    => 'Couleur souhaitée',
                    'type'     => 'text',
                    'required' => false,
                    'max_length' => 120,
                    'help'     => 'Référence couleur ou description libre.',
                ],
                [
                    'key'      => 'preparation_necessaire',
                    'label'    => 'Préparation nécessaire (rebouchage, ponçage)',
                    'type'     => 'boolean',
                    'default'  => false,
                    'pricing'  => ['modifier' => 'percent', 'value' => 20],
                ],
                [
                    'key'      => 'fournitures_incluses',
                    'label'    => 'Fournitures incluses dans la prestation',
                    'type'     => 'boolean',
                    'default'  => false,
                ],
            ],
        ];
    }

    /**
     * Phase F3 — schema dynamique du métier Levage / Manutention.
     */
    public static function liftingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key'      => 'type_engin',
                    'label'    => 'Type d\'engin requis',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => [
                        ['value' => 'nacelle',     'label' => 'Nacelle',         'price_delta' => 0],
                        ['value' => 'chariot',     'label' => 'Chariot élévateur','price_delta' => 0],
                        ['value' => 'monte_charge','label' => 'Monte-charge',    'price_delta' => 0],
                        ['value' => 'grue',        'label' => 'Grue mobile',     'price_delta' => 200],
                    ],
                ],
                [
                    'key'      => 'hauteur_intervention',
                    'label'    => 'Hauteur d\'intervention',
                    'type'     => 'number',
                    'unit'     => 'm',
                    'required' => true,
                    'min'      => 0,
                    'max'      => 100,
                    'step'     => 0.5,
                ],
                [
                    'key'      => 'duree_estimee_heures',
                    'label'    => 'Durée estimée',
                    'type'     => 'number',
                    'unit'     => 'heures',
                    'required' => true,
                    'min'      => 1,
                    'max'      => 100,
                    'step'     => 1,
                    'pricing'  => ['modifier' => 'per_unit', 'value' => 80],
                ],
                [
                    'key'      => 'instructions_acces',
                    'label'    => 'Instructions d\'accès au site',
                    'type'     => 'textarea',
                    'required' => false,
                    'max_length' => 1000,
                ],
            ],
        ];
    }

    /**
     * Phase F3 — schema dynamique du métier Jardinage.
     */
    public static function gardeningBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key'      => 'type_intervention',
                    'label'    => 'Type d\'intervention',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => [
                        ['value' => 'tonte',       'label' => 'Tonte',           'price_delta' => 0],
                        ['value' => 'taille',      'label' => 'Taille de haie',  'price_delta' => 0],
                        ['value' => 'elagage',     'label' => 'Élagage',         'price_delta' => 30],
                        ['value' => 'desherbage',  'label' => 'Désherbage',      'price_delta' => 0],
                        ['value' => 'plantation',  'label' => 'Plantation',      'price_delta' => 0],
                        ['value' => 'entretien_complet', 'label' => 'Entretien complet', 'price_delta' => 50],
                    ],
                ],
                [
                    'key'      => 'surface',
                    'label'    => 'Surface du jardin',
                    'type'     => 'number',
                    'unit'     => 'm²',
                    'required' => true,
                    'min'      => 1,
                    'max'      => 10000,
                    'step'     => 10,
                ],
                [
                    'key'      => 'evacuation_dechets',
                    'label'    => 'Évacuation des déchets verts incluse',
                    'type'     => 'boolean',
                    'default'  => false,
                    'pricing'  => ['modifier' => 'fixed', 'value' => 25],
                ],
                [
                    'key'      => 'acces_jardin',
                    'label'    => 'Précisions sur l\'accès',
                    'type'     => 'textarea',
                    'required' => false,
                    'max_length' => 500,
                ],
            ],
        ];
    }
}
