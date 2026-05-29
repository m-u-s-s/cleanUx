<?php

namespace Database\Seeders;

use App\Models\Trade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the 12 canonical trades (corps de métier) for the multi-trade marketplace.
 *
 * Idempotent: uses updateOrCreate keyed on slug.
 * Defensive: only sets billing_unit / requires_site_visit if those columns exist.
 */
class TradeSeeder extends Seeder
{
    public function run(): void
    {
        $hasBillingUnit = Schema::hasColumn('trades', 'billing_unit');
        $hasRequiresSiteVisit = Schema::hasColumn('trades', 'requires_site_visit');

        foreach (self::trades() as $payload) {
            $core = [
                'code' => $payload['code'],
                'name' => $payload['name'],
                'icon' => $payload['icon'],
                'color' => $payload['color'],
                'short_description' => $payload['short_description'],
                'description' => $payload['description'],
                'is_active' => true,
                'requires_certification' => $payload['requires_certification'] ?? false,
                'requires_insurance_proof' => $payload['requires_insurance_proof'] ?? false,
                'is_b2b_default' => $payload['is_b2b_default'] ?? true,
                'is_personal_default' => $payload['is_personal_default'] ?? true,
                'sort_order' => $payload['sort_order'],
                'booking_form_schema' => $payload['booking_form_schema'],
            ];

            if ($hasBillingUnit) {
                $core['billing_unit'] = $payload['billing_unit'];
            }

            if ($hasRequiresSiteVisit) {
                $core['requires_site_visit'] = $payload['requires_site_visit'];
            }

            Trade::updateOrCreate(['slug' => $payload['slug']], $core);
        }

        // Back-compat: if a 'nettoyage' slug exists, ensure 'cleaning' also exists
        // so migration A1 backfill can find it by either slug.
        $nettoyage = Trade::where('slug', 'nettoyage')->first();
        if ($nettoyage && ! Trade::where('slug', 'cleaning')->exists()) {
            // Already seeded as 'cleaning' above — nothing to do.
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Trade definitions
    // ──────────────────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private static function trades(): array
    {
        return [
            [
                'slug' => 'nettoyage',
                'code' => 'CLN',
                'name' => 'Nettoyage',
                'icon' => 'broom',
                'color' => '#0EA5E9',
                'billing_unit' => 'hourly',
                'requires_site_visit' => false,
                'short_description' => 'Nettoyage récurrent ou ponctuel pour particuliers et entreprises.',
                'description' => 'Tous types de prestations de nettoyage : domestique, bureaux, vitrerie, fin de chantier, désinfection.',
                'requires_certification' => false,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 10,
                'booking_form_schema' => self::cleaningBookingFormSchema(),
            ],
            [
                'slug' => 'peinture',
                'code' => 'PNT',
                'name' => 'Peinture',
                'icon' => 'paint-brush',
                'color' => '#A855F7',
                'billing_unit' => 'per_m2',
                'requires_site_visit' => true,
                'short_description' => 'Peinture intérieure, extérieure et décoration murale.',
                'description' => 'Peinture, enduits, revêtements muraux, papiers peints, ravalement de façade.',
                'requires_certification' => false,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 20,
                'booking_form_schema' => self::paintingBookingFormSchema(),
            ],
            [
                'slug' => 'batiment',
                'code' => 'BLD',
                'name' => 'Batiment / Gros oeuvre',
                'icon' => 'hammer',
                'color' => '#F59E0B',
                'billing_unit' => 'fixed',
                'requires_site_visit' => true,
                'short_description' => 'Travaux de construction, renovation et maconnerie.',
                'description' => 'Gros oeuvre, second oeuvre, isolation, platerie, carrelage, etancheite.',
                'requires_certification' => true,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 30,
                'booking_form_schema' => self::buildingBookingFormSchema(),
            ],
            [
                'slug' => 'plumbing',
                'code' => 'PLB',
                'name' => 'Plomberie',
                'icon' => 'wrench',
                'color' => '#3B82F6',
                'billing_unit' => 'hourly',
                'requires_site_visit' => false,
                'short_description' => 'Depannage, installation et entretien de plomberie.',
                'description' => 'Depannage urgent, installation sanitaire, debouchage, chauffe-eau, radiateurs.',
                'requires_certification' => false,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 40,
                'booking_form_schema' => self::plumbingBookingFormSchema(),
            ],
            [
                'slug' => 'electrical',
                'code' => 'ELC',
                'name' => 'Electricite',
                'icon' => 'bolt',
                'color' => '#EAB308',
                'billing_unit' => 'hourly',
                'requires_site_visit' => false,
                'short_description' => 'Depannage, installation et mise en conformite electrique.',
                'description' => 'Depannage electrique, mise en conformite, installation tableau, eclairage, domotique.',
                'requires_certification' => true,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 50,
                'booking_form_schema' => self::electricalBookingFormSchema(),
            ],
            [
                'slug' => 'jardinage',
                'code' => 'GRD',
                'name' => 'Jardinage',
                'icon' => 'leaf',
                'color' => '#22C55E',
                'billing_unit' => 'hourly',
                'requires_site_visit' => false,
                'short_description' => 'Entretien, amenagement, elagage et creation paysagere.',
                'description' => 'Tonte, taille, elagage, desherbage, plantation, creation de jardins.',
                'requires_certification' => false,
                'requires_insurance_proof' => false,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 60,
                'booking_form_schema' => self::gardeningBookingFormSchema(),
            ],
            [
                'slug' => 'moving',
                'code' => 'MOV',
                'name' => 'Demenagement',
                'icon' => 'truck',
                'color' => '#F97316',
                'billing_unit' => 'fixed',
                'requires_site_visit' => true,
                'short_description' => 'Demenagement particuliers et entreprises, toutes distances.',
                'description' => 'Demenagement complet, emballage, transport, montage meubles, stockage.',
                'requires_certification' => false,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 70,
                'booking_form_schema' => self::movingBookingFormSchema(),
            ],
            [
                'slug' => 'childcare',
                'code' => 'CHD',
                'name' => 'Garde d\'enfants',
                'icon' => 'user-group',
                'color' => '#EC4899',
                'billing_unit' => 'hourly',
                'requires_site_visit' => false,
                'short_description' => 'Garde a domicile, babysitting et assistante maternelle.',
                'description' => 'Garde a domicile ponctuelle ou reguliere, babysitting soir et week-end, aide aux devoirs.',
                'requires_certification' => false,
                'requires_insurance_proof' => false,
                'is_b2b_default' => false,
                'is_personal_default' => true,
                'sort_order' => 80,
                'booking_form_schema' => self::childcareBookingFormSchema(),
            ],
            [
                'slug' => 'roofing',
                'code' => 'ROF',
                'name' => 'Toiture',
                'icon' => 'home',
                'color' => '#78716C',
                'billing_unit' => 'per_m2',
                'requires_site_visit' => true,
                'short_description' => 'Reparation, renovation et pose de toitures.',
                'description' => 'Reparation toiture, remplacement tuiles, etancheite, zinguerie, isolation combles.',
                'requires_certification' => true,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 90,
                'booking_form_schema' => self::roofingBookingFormSchema(),
            ],
            [
                'slug' => 'levage',
                'code' => 'LFT',
                'name' => 'Levage / Lift',
                'icon' => 'arrow-up',
                'color' => '#EF4444',
                'billing_unit' => 'fixed',
                'requires_site_visit' => true,
                'short_description' => 'Engins de levage, nacelles, monte-charges, demenagement lourd.',
                'description' => 'Location avec operateur certifie, manutention industrielle, chantiers en hauteur.',
                'requires_certification' => true,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => false,
                'sort_order' => 100,
                'booking_form_schema' => self::liftingBookingFormSchema(),
            ],
            [
                'slug' => 'renovation',
                'code' => 'RNV',
                'name' => 'Renovation',
                'icon' => 'pencil-square',
                'color' => '#14B8A6',
                'billing_unit' => 'per_m2',
                'requires_site_visit' => true,
                'short_description' => 'Renovation interieure et exterieure tous corps de metier.',
                'description' => 'Renovation complete ou partielle : sol, murs, cuisine, salle de bain, isolation.',
                'requires_certification' => false,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => true,
                'sort_order' => 110,
                'booking_form_schema' => self::renovationBookingFormSchema(),
            ],
            [
                'slug' => 'security',
                'code' => 'SEC',
                'name' => 'Securite / Gardiennage',
                'icon' => 'shield-check',
                'color' => '#1E40AF',
                'billing_unit' => 'hourly',
                'requires_site_visit' => false,
                'short_description' => 'Agents de securite, gardiennage et surveillance.',
                'description' => 'Gardiennage de site, securite evenementielle, rondes, telesurveillance.',
                'requires_certification' => true,
                'requires_insurance_proof' => true,
                'is_b2b_default' => true,
                'is_personal_default' => false,
                'sort_order' => 120,
                'booking_form_schema' => self::securityBookingFormSchema(),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Booking form schemas
    // ──────────────────────────────────────────────────────────────────────────

    public static function cleaningBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_lieu',
                    'label' => 'Type de lieu',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'appartement', 'label' => 'Appartement', 'price_delta' => 0],
                        ['value' => 'maison',      'label' => 'Maison',      'price_delta' => 0],
                        ['value' => 'bureau',      'label' => 'Bureau',      'price_delta' => 0],
                        ['value' => 'commerce',    'label' => 'Commerce',    'price_delta' => 0],
                        ['value' => 'autre',       'label' => 'Autre',       'price_delta' => 0],
                    ],
                ],
                [
                    'key' => 'surface',
                    'label' => 'Surface',
                    'type' => 'select',
                    'required' => true,
                    'unit' => 'm2',
                    'options' => [
                        ['value' => 'moins_50', 'label' => 'Moins de 50 m2',  'price_delta' => 0],
                        ['value' => '50_100',   'label' => '50 a 100 m2',     'price_delta' => 20],
                        ['value' => '100_150',  'label' => '100 a 150 m2',    'price_delta' => 45],
                        ['value' => '150_250',  'label' => '150 a 250 m2',    'price_delta' => 80],
                        ['value' => 'plus_250', 'label' => 'Plus de 250 m2',  'price_delta' => 130],
                    ],
                ],
                [
                    'key' => 'frequence',
                    'label' => 'Frequence',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'ponctuel',  'label' => 'Ponctuel',     'price_delta' => 0],
                        ['value' => 'hebdo',     'label' => 'Hebdomadaire', 'price_delta' => 0],
                        ['value' => 'bimensuel', 'label' => 'Bimensuel',    'price_delta' => 0],
                        ['value' => 'mensuel',   'label' => 'Mensuel',      'price_delta' => 0],
                    ],
                ],
                [
                    'key' => 'options_prestation',
                    'label' => 'Options supplementaires',
                    'type' => 'multiselect',
                    'required' => false,
                    'options' => [
                        ['value' => 'vitres',       'label' => 'Nettoyage des vitres', 'price_delta' => 15],
                        ['value' => 'frigo',        'label' => 'Interieur frigo',      'price_delta' => 10],
                        ['value' => 'four',         'label' => 'Interieur four',       'price_delta' => 10],
                        ['value' => 'repassage',    'label' => 'Repassage',            'price_delta' => 20],
                        ['value' => 'desinfection', 'label' => 'Desinfection complete', 'price_delta' => 25],
                    ],
                ],
                [
                    'key' => 'zones_specifiques',
                    'label' => 'Zones specifiques a nettoyer',
                    'type' => 'multiselect',
                    'required' => false,
                    'options' => [
                        ['value' => 'cuisine',      'label' => 'Cuisine',        'price_delta' => 0],
                        ['value' => 'salle_bain',   'label' => 'Salle de bain',  'price_delta' => 0],
                        ['value' => 'salon',        'label' => 'Salon',          'price_delta' => 0],
                        ['value' => 'chambres',     'label' => 'Chambres',       'price_delta' => 0],
                        ['value' => 'cave_grenier', 'label' => 'Cave / Grenier', 'price_delta' => 5],
                    ],
                ],
                [
                    'key' => 'presence_animaux',
                    'label' => 'Presence d\'animaux domestiques',
                    'type' => 'boolean',
                    'default' => false,
                ],
                [
                    'key' => 'acces_parking',
                    'label' => 'Parking disponible sur place',
                    'type' => 'boolean',
                    'default' => false,
                ],
                [
                    'key' => 'materiel_fournit',
                    'label' => 'Je fournis le materiel',
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ];
    }

    public static function paintingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_surface',
                    'label' => 'Surface a peindre',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'murs',      'label' => 'Murs interieurs',   'price_delta' => 0],
                        ['value' => 'plafonds',  'label' => 'Plafonds',          'price_delta' => 10],
                        ['value' => 'boiseries', 'label' => 'Boiseries',         'price_delta' => 20],
                        ['value' => 'facade',    'label' => 'Facade exterieure', 'price_delta' => 50],
                        ['value' => 'mix',       'label' => 'Plusieurs zones',   'price_delta' => 30],
                    ],
                ],
                [
                    'key' => 'surface',
                    'label' => 'Surface a peindre',
                    'type' => 'number',
                    'unit' => 'm2',
                    'required' => true,
                    'min' => 1,
                    'max' => 5000,
                ],
                [
                    'key' => 'preparation_necessaire',
                    'label' => 'Preparation necessaire (rebouchage, poncage)',
                    'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'percent', 'value' => 20],
                ],
                [
                    'key' => 'fournitures_incluses',
                    'label' => 'Fournitures incluses dans la prestation',
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ];
    }

    public static function buildingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_intervention',
                    'label' => 'Type d\'intervention',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'gros_oeuvre',   'label' => 'Gros oeuvre',   'price_delta' => 0],
                        ['value' => 'second_oeuvre', 'label' => 'Second oeuvre', 'price_delta' => 0],
                        ['value' => 'renovation',    'label' => 'Renovation',    'price_delta' => 0],
                        ['value' => 'isolation',     'label' => 'Isolation',     'price_delta' => 0],
                        ['value' => 'etancheite',    'label' => 'Etancheite',    'price_delta' => 0],
                    ],
                ],
                [
                    'key' => 'surface',
                    'label' => 'Surface concernee',
                    'type' => 'number',
                    'unit' => 'm2',
                    'required' => true,
                    'min' => 1,
                    'max' => 10000,
                ],
                [
                    'key' => 'details_techniques',
                    'label' => 'Details techniques',
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 2000,
                ],
            ],
        ];
    }

    public static function plumbingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_intervention',
                    'label' => 'Type d\'intervention',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'depannage_urgent',    'label' => 'Depannage urgent',       'price_delta' => 30],
                        ['value' => 'installation',        'label' => 'Installation sanitaire', 'price_delta' => 0],
                        ['value' => 'debouchage',          'label' => 'Debouchage',             'price_delta' => 0],
                        ['value' => 'chauffe_eau',         'label' => 'Chauffe-eau',            'price_delta' => 0],
                        ['value' => 'diagnostic',          'label' => 'Diagnostic',             'price_delta' => 0],
                    ],
                ],
                [
                    'key' => 'urgence',
                    'label' => 'Intervention urgente (< 4h)',
                    'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'percent', 'value' => 50],
                ],
                [
                    'key' => 'description',
                    'label' => 'Description du probleme',
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 1000,
                ],
            ],
        ];
    }

    public static function electricalBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_intervention',
                    'label' => 'Type d\'intervention',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'depannage',       'label' => 'Depannage',           'price_delta' => 20],
                        ['value' => 'mise_conformite', 'label' => 'Mise en conformite',  'price_delta' => 0],
                        ['value' => 'tableau',         'label' => 'Installation tableau', 'price_delta' => 0],
                        ['value' => 'eclairage',       'label' => 'Eclairage',           'price_delta' => 0],
                        ['value' => 'domotique',       'label' => 'Domotique',           'price_delta' => 0],
                    ],
                ],
                [
                    'key' => 'urgence',
                    'label' => 'Urgence (panne totale)',
                    'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'percent', 'value' => 40],
                ],
                [
                    'key' => 'description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 1000,
                ],
            ],
        ];
    }

    public static function gardeningBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_intervention',
                    'label' => 'Type d\'intervention',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'tonte',            'label' => 'Tonte',            'price_delta' => 0],
                        ['value' => 'taille',           'label' => 'Taille de haie',   'price_delta' => 0],
                        ['value' => 'elagage',          'label' => 'Elagage',          'price_delta' => 30],
                        ['value' => 'desherbage',       'label' => 'Desherbage',       'price_delta' => 0],
                        ['value' => 'plantation',       'label' => 'Plantation',       'price_delta' => 0],
                        ['value' => 'entretien_complet', 'label' => 'Entretien complet', 'price_delta' => 50],
                    ],
                ],
                [
                    'key' => 'surface',
                    'label' => 'Surface du jardin',
                    'type' => 'number',
                    'unit' => 'm2',
                    'required' => true,
                    'min' => 1,
                    'max' => 10000,
                ],
                [
                    'key' => 'evacuation_dechets',
                    'label' => 'Evacuation des dechets verts incluse',
                    'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'fixed', 'value' => 25],
                ],
            ],
        ];
    }

    public static function movingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_logement',
                    'label' => 'Type de logement',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'studio',    'label' => 'Studio',        'price_delta' => 0],
                        ['value' => 't2',        'label' => 'T2',            'price_delta' => 100],
                        ['value' => 't3',        'label' => 'T3',            'price_delta' => 200],
                        ['value' => 't4_plus',   'label' => 'T4 et plus',    'price_delta' => 350],
                        ['value' => 'maison',    'label' => 'Maison',        'price_delta' => 400],
                        ['value' => 'bureaux',   'label' => 'Bureaux',       'price_delta' => 500],
                    ],
                ],
                [
                    'key' => 'etage',
                    'label' => 'Etage (sans ascenseur)',
                    'type' => 'number',
                    'unit' => 'etage',
                    'required' => false,
                    'min' => 0,
                    'max' => 20,
                    'pricing' => ['modifier' => 'per_unit', 'value' => 30],
                ],
                [
                    'key' => 'emballage_inclus',
                    'label' => 'Emballage inclus',
                    'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'percent', 'value' => 20],
                ],
                [
                    'key' => 'monte_meuble',
                    'label' => 'Monte-meuble requis',
                    'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'fixed', 'value' => 150],
                ],
            ],
        ];
    }

    public static function childcareBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'age_enfant',
                    'label' => 'Age du plus jeune enfant',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'moins_1_an', 'label' => 'Moins de 1 an',  'price_delta' => 5],
                        ['value' => '1_3_ans',    'label' => '1 a 3 ans',      'price_delta' => 3],
                        ['value' => '3_6_ans',    'label' => '3 a 6 ans',      'price_delta' => 0],
                        ['value' => '6_12_ans',   'label' => '6 a 12 ans',     'price_delta' => 0],
                        ['value' => 'plus_12_ans', 'label' => 'Plus de 12 ans', 'price_delta' => 0],
                    ],
                ],
                [
                    'key' => 'nombre_enfants',
                    'label' => 'Nombre d\'enfants',
                    'type' => 'number',
                    'required' => true,
                    'min' => 1,
                    'max' => 10,
                    'pricing' => ['modifier' => 'per_unit_after_first', 'value' => 3],
                ],
                [
                    'key' => 'aide_devoirs',
                    'label' => 'Aide aux devoirs',
                    'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'fixed', 'value' => 5],
                ],
            ],
        ];
    }

    public static function roofingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_intervention',
                    'label' => 'Type d\'intervention',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'reparation',   'label' => 'Reparation tuiles/ardoises', 'price_delta' => 0],
                        ['value' => 'etancheite',   'label' => 'Etancheite / membrane',     'price_delta' => 0],
                        ['value' => 'zinguerie',    'label' => 'Zinguerie',                 'price_delta' => 0],
                        ['value' => 'renovation',   'label' => 'Renovation complete',       'price_delta' => 0],
                        ['value' => 'nettoyage',    'label' => 'Nettoyage toiture',         'price_delta' => 0],
                    ],
                ],
                [
                    'key' => 'surface',
                    'label' => 'Surface de toiture',
                    'type' => 'number',
                    'unit' => 'm2',
                    'required' => true,
                    'min' => 5,
                    'max' => 2000,
                ],
                [
                    'key' => 'acces_difficile',
                    'label' => 'Acces difficile (hauteur > 8m)',
                    'type' => 'boolean',
                    'default' => false,
                    'pricing' => ['modifier' => 'percent', 'value' => 30],
                ],
            ],
        ];
    }

    public static function liftingBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_engin',
                    'label' => 'Type d\'engin requis',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'nacelle',      'label' => 'Nacelle',          'price_delta' => 0],
                        ['value' => 'chariot',      'label' => 'Chariot elevateur', 'price_delta' => 0],
                        ['value' => 'monte_charge', 'label' => 'Monte-charge',     'price_delta' => 0],
                        ['value' => 'grue',         'label' => 'Grue mobile',      'price_delta' => 200],
                    ],
                ],
                [
                    'key' => 'hauteur_intervention',
                    'label' => 'Hauteur d\'intervention',
                    'type' => 'number',
                    'unit' => 'm',
                    'required' => true,
                    'min' => 0,
                    'max' => 100,
                ],
                [
                    'key' => 'duree_estimee_heures',
                    'label' => 'Duree estimee',
                    'type' => 'number',
                    'unit' => 'heures',
                    'required' => true,
                    'min' => 1,
                    'max' => 100,
                    'pricing' => ['modifier' => 'per_unit', 'value' => 80],
                ],
                [
                    'key' => 'instructions_acces',
                    'label' => 'Instructions d\'acces au site',
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 1000,
                ],
            ],
        ];
    }

    public static function renovationBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_renovation',
                    'label' => 'Type de renovation',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'cuisine',    'label' => 'Cuisine',         'price_delta' => 0],
                        ['value' => 'sdb',        'label' => 'Salle de bain',   'price_delta' => 0],
                        ['value' => 'sol',        'label' => 'Revetement sol',  'price_delta' => 0],
                        ['value' => 'complete',   'label' => 'Renovation totale', 'price_delta' => 0],
                        ['value' => 'facade',     'label' => 'Facade',          'price_delta' => 0],
                    ],
                ],
                [
                    'key' => 'surface',
                    'label' => 'Surface a renover',
                    'type' => 'number',
                    'unit' => 'm2',
                    'required' => true,
                    'min' => 1,
                    'max' => 500,
                ],
                [
                    'key' => 'details',
                    'label' => 'Description des travaux',
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 2000,
                ],
            ],
        ];
    }

    public static function securityBookingFormSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [
                [
                    'key' => 'type_mission',
                    'label' => 'Type de mission',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'gardiennage',    'label' => 'Gardiennage de site',      'price_delta' => 0],
                        ['value' => 'evenementiel',   'label' => 'Securite evenementielle',  'price_delta' => 0],
                        ['value' => 'rondes',         'label' => 'Rondes de surveillance',   'price_delta' => 0],
                        ['value' => 'intervention',   'label' => 'Agent d\'intervention',    'price_delta' => 30],
                    ],
                ],
                [
                    'key' => 'nombre_agents',
                    'label' => 'Nombre d\'agents',
                    'type' => 'number',
                    'required' => true,
                    'min' => 1,
                    'max' => 50,
                    'pricing' => ['modifier' => 'per_unit', 'value' => 100],
                ],
                [
                    'key' => 'tenue_officielle',
                    'label' => 'Tenue officielle requise',
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
        ];
    }
}
