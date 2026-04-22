<?php

namespace Database\Seeders;

use App\Models\Disponibilite;
use App\Models\EmployeeZoneAssignment;
use App\Models\Feedback;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $zones = ServiceZone::query()
            ->where('coverage_type', 'province')
            ->get()
            ->keyBy('code');

        $postalCodes = PostalCode::query()
            ->get()
            ->keyBy(fn(PostalCode $postalCode) => $postalCode->code . '-' . $postalCode->city_name);

        $services = ServiceCatalog::query()
            ->get()
            ->keyBy('service_type');

        User::updateOrCreate(
            ['email' => 'admin@cleanux.test'],
            [
                'name' => 'Admin cleanux',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'status' => 'active',
                'is_active' => true,
                'locale' => 'fr_BE',
                'timezone' => 'Europe/Brussels',
                'access_scope' => User::ACCESS_SCOPE_ALL,
                'permissions' => ['*'],
            ]
        );

        $entrepriseAccount = OrganizationAccount::updateOrCreate(
            ['name' => 'Atlas Facilities Belgium'],
            [
                'legal_name' => 'Atlas Facilities Belgium SA',
                'slug' => 'atlas-facilities-belgium',
                'type' => 'entreprise',
                'tva_number' => 'BE0123456789',
                'email' => 'ops@atlasfacilities.test',
                'billing_email' => 'finance@atlasfacilities.test',
                'phone' => '+3225550101',
                'status' => 'active',
                'is_multisite' => true,
                'is_key_account' => true,
                'postal_code_id' => optional($postalCodes->get('1000-Bruxelles'))->id,
                'city' => 'Bruxelles',
                'postal_code' => '1000',
                'address_line_1' => 'Rue de l\'Industrie 12',
                'metadata' => [
                    'customer_segment' => 'entreprise',
                    'pricing_profile' => 'negotiated',
                ],
            ]
        );

        $entrepriseContact = User::updateOrCreate(
            ['email' => 'facilities@atlasfacilities.test'],
            [
                'name' => 'Atlas Facilities Manager',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ENTREPRISE,
                'tva_number' => 'BE0123456789',
                'plan_type' => 'premium',
                'plan_status' => 'active',
                'premium_started_at' => now()->subMonth(),
                'premium_renewal_at' => now()->addMonth(),
                'organization_account_id' => $entrepriseAccount->id,
                'postal_code_id' => optional($postalCodes->get('1000-Bruxelles'))->id,
                'primary_service_zone_id' => optional($zones->get('PROV-BRU'))->id,
                'phone' => '+32471111222',
                'status' => 'active',
                'is_active' => true,
                'metadata' => [
                    'entreprise_context' => [
                        'contact_role' => 'facilities_manager',
                        'site_scope' => 'all',
                    ],
                ],
            ]
        );

        $premiumClient = User::updateOrCreate(
            ['email' => 'premium.client@cleanux.test'],
            [
                'name' => 'Client Premium Bruxelles',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CLIENT,
                'plan_type' => 'premium',
                'plan_status' => 'active',
                'premium_started_at' => now()->subWeeks(2),
                'premium_renewal_at' => now()->addWeeks(2),
                'postal_code_id' => optional($postalCodes->get('1050-Ixelles'))->id,
                'primary_service_zone_id' => optional($zones->get('PROV-BRU'))->id,
                'phone' => '+32470000111',
                'status' => 'active',
                'is_active' => true,
            ]
        );

        $standardClient = User::updateOrCreate(
            ['email' => 'client.standard@cleanux.test'],
            [
                'name' => 'Client Standard Gand',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CLIENT,
                'plan_type' => 'standard',
                'plan_status' => 'inactive',
                'postal_code_id' => optional($postalCodes->get('9000-Gand'))->id,
                'primary_service_zone_id' => optional($zones->get('PROV-OVL'))->id,
                'phone' => '+32470000999',
                'status' => 'active',
                'is_active' => true,
            ]
        );

        $employees = [
            [
                'email' => 'bruxelles.team@cleanux.test',
                'name' => 'Equipe Bruxelles',
                'zone' => 'PROV-BRU',
                'postal' => '1000-Bruxelles',
            ],
            [
                'email' => 'anvers.team@cleanux.test',
                'name' => 'Equipe Anvers',
                'zone' => 'PROV-ANT',
                'postal' => '2000-Anvers',
            ],
            [
                'email' => 'gand.team@cleanux.test',
                'name' => 'Equipe Gand',
                'zone' => 'PROV-OVL',
                'postal' => '9000-Gand',
            ],
            [
                'email' => 'liege.team@cleanux.test',
                'name' => 'Equipe Liège',
                'zone' => 'PROV-LIE',
                'postal' => '4000-Liège',
            ],
            [
                'email' => 'namur.team@cleanux.test',
                'name' => 'Equipe Namur',
                'zone' => 'PROV-NAM',
                'postal' => '5000-Namur',
            ],
        ];

        $employeeModels = collect($employees)->map(function (array $employee) use ($zones, $postalCodes) {
            $zone = $zones[$employee['zone']] ?? null;
            $postalCode = $postalCodes[$employee['postal']] ?? null;

            return User::updateOrCreate(
                ['email' => $employee['email']],
                [
                    'name' => $employee['name'],
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_EMPLOYE,
                    'postal_code_id' => optional($postalCode)->id,
                    'primary_service_zone_id' => optional($zone)->id,
                    'phone' => '+3246' . random_int(1000000, 9999999),
                    'status' => 'active',
                    'is_active' => true,
                ]
            );
        })->keyBy('email');

        foreach ($employeeModels as $employee) {
            if ($employee->primary_service_zone_id) {
                EmployeeZoneAssignment::updateOrCreate(
                    [
                        'user_id' => $employee->id,
                        'service_zone_id' => $employee->primary_service_zone_id,
                        'assignment_type' => 'primary',
                    ],
                    [
                        'coverage_priority' => 10,
                        'is_active' => true,
                        'starts_at' => now()->subMonth(),
                    ]
                );
            }
        }

        $hqSite = OrganizationSite::updateOrCreate(
            [
                'organization_account_id' => $entrepriseAccount->id,
                'site_code' => 'BRU-HQ',
            ],
            [
                'client_user_id' => $entrepriseContact->id,
                'service_zone_id' => optional($zones->get('PROV-BRU'))->id,
                'postal_code_id' => optional($postalCodes->get('1000-Bruxelles'))->id,
                'name' => 'Siège Bruxelles',
                'contact_name' => 'Atlas Front Desk',
                'email' => 'hq@atlasfacilities.test',
                'phone' => '+3225550102',
                'address_line_1' => 'Rue de l\'Industrie 12',
                'city' => 'Bruxelles',
                'postal_code' => '1000',
                'is_primary' => true,
                'is_active' => true,
            ]
        );

        $gentSite = OrganizationSite::updateOrCreate(
            [
                'organization_account_id' => $entrepriseAccount->id,
                'site_code' => 'GNT-OPS',
            ],
            [
                'client_user_id' => $entrepriseContact->id,
                'service_zone_id' => optional($zones->get('PROV-OVL'))->id,
                'postal_code_id' => optional($postalCodes->get('9000-Gand'))->id,
                'name' => 'Hub Gand',
                'contact_name' => 'Atlas Ops Gent',
                'email' => 'gent@atlasfacilities.test',
                'phone' => '+3295550102',
                'address_line_1' => 'Kouter 18',
                'city' => 'Gand',
                'postal_code' => '9000',
                'is_primary' => false,
                'is_active' => true,
            ]
        );

        $premiumClient->favoriteEmployes()->syncWithoutDetaching([
            $employeeModels['bruxelles.team@cleanux.test']->id => ['is_favorite' => true],
            $employeeModels['anvers.team@cleanux.test']->id => ['is_favorite' => true],
        ]);

        foreach ($employeeModels as $employee) {
            foreach (range(0, 6) as $day) {
                foreach (['09:00:00', '09:30:00', '10:00:00', '14:00:00', '14:30:00', '15:00:00'] as $slot) {
                    Disponibilite::updateOrCreate(
                        [
                            'user_id' => $employee->id,
                            'date' => now()->startOfDay()->addDays($day)->toDateString(),
                            'heure_debut' => $slot,
                        ],
                        [
                            'heure_fin' => date('H:i:s', strtotime($slot . ' +30 minutes')),
                        ]
                    );
                }
            }
        }

        $seriesId = '11111111-1111-1111-1111-111111111111';

        $appointments = [
            [
                'reference' => 'PREM-BRU-001',
                'client' => $premiumClient,
                'employee' => $employeeModels['bruxelles.team@cleanux.test'],
                'service' => $services['nettoyage_profond'],
                'zone' => $zones['PROV-BRU'] ?? null,
                'postal' => $postalCodes['1050-Ixelles'] ?? null,
                'site' => null,
                'status' => 'en_attente',
                'date' => now()->addDays(2)->toDateString(),
                'heure' => '09:00:00',
                'adresse' => 'Avenue Louise 120',
                'ville' => 'Ixelles',
                'code_postal' => '1050',
                'type_lieu' => 'appartement',
                'surface' => '50_100',
                'frequence' => 'mensuel',
                'devis_estime' => 149,
                'is_recurrent' => true,
                'recurring_series_id' => $seriesId,
                'recurrence_rule' => 'FREQ=MONTHLY;INTERVAL=1',
                'recurrence_frequency' => 'monthly',
                'recurrence_interval' => 1,
                'recurrence_until' => now()->addMonths(3)->toDateString(),
                'recurrence_count' => 4,
                'recurrence_days' => null,
                'is_series_master' => true,
                'series_position' => 1,
                'series_status' => 'active',
                'options_prestation' => ['vitres', 'cuisine'],
                'zones_specifiques' => ['salon', 'cuisine'],
            ],
            [
                'reference' => 'STD-GNT-001',
                'client' => $standardClient,
                'employee' => $employeeModels['gand.team@cleanux.test'],
                'service' => $services['nettoyage_standard'],
                'zone' => $zones['PROV-OVL'] ?? null,
                'postal' => $postalCodes['9000-Gand'] ?? null,
                'site' => null,
                'status' => 'confirme',
                'date' => now()->addDays(1)->toDateString(),
                'heure' => '14:00:00',
                'adresse' => 'Kortrijksesteenweg 45',
                'ville' => 'Gand',
                'code_postal' => '9000',
                'type_lieu' => 'maison',
                'surface' => 'moins_50',
                'frequence' => 'ponctuel',
                'devis_estime' => 79,
            ],
            [
                'reference' => 'ENT-BRU-001',
                'client' => $entrepriseContact,
                'employee' => $employeeModels['bruxelles.team@cleanux.test'],
                'service' => $services['bureaux'],
                'zone' => $zones['PROV-BRU'] ?? null,
                'postal' => $postalCodes['1000-Bruxelles'] ?? null,
                'site' => $hqSite,
                'status' => 'termine',
                'date' => now()->subDays(3)->toDateString(),
                'heure' => '08:30:00',
                'adresse' => 'Rue de l\'Industrie 12',
                'ville' => 'Bruxelles',
                'code_postal' => '1000',
                'type_lieu' => 'bureaux',
                'surface' => '150_250',
                'frequence' => 'hebdomadaire',
                'devis_estime' => 219,
                'is_recurrent' => true,
                'recurring_series_id' => '22222222-2222-2222-2222-222222222222',
                'recurrence_rule' => 'FREQ=WEEKLY;INTERVAL=1',
                'recurrence_frequency' => 'weekly',
                'recurrence_interval' => 1,
                'recurrence_until' => now()->addWeeks(6)->toDateString(),
                'recurrence_count' => 6,
                'recurrence_days' => ['monday'],
                'is_series_master' => true,
                'series_position' => 1,
                'series_status' => 'active',
                'options_prestation' => ['open-space', 'sanitaires'],
                'zones_specifiques' => ['open-space', 'salle-reunion'],
                'materiel_specifique' => ['autolaveuse'],
            ],
            [
                'reference' => 'ENT-GNT-002',
                'client' => $entrepriseContact,
                'employee' => $employeeModels['gand.team@cleanux.test'],
                'service' => $services['bureaux'],
                'zone' => $zones['PROV-OVL'] ?? null,
                'postal' => $postalCodes['9000-Gand'] ?? null,
                'site' => $gentSite,
                'status' => 'confirme',
                'date' => now()->addDays(4)->toDateString(),
                'heure' => '07:30:00',
                'adresse' => 'Kouter 18',
                'ville' => 'Gand',
                'code_postal' => '9000',
                'type_lieu' => 'bureaux',
                'surface' => '100_150',
                'frequence' => 'hebdomadaire',
                'devis_estime' => 199,
                'is_recurrent' => true,
                'recurring_series_id' => '22222222-2222-2222-2222-222222222222',
                'recurrence_rule' => 'FREQ=WEEKLY;INTERVAL=1',
                'recurrence_frequency' => 'weekly',
                'recurrence_interval' => 1,
                'recurrence_until' => now()->addWeeks(6)->toDateString(),
                'recurrence_count' => 6,
                'recurrence_days' => ['monday'],
                'is_series_master' => false,
                'series_position' => 2,
                'series_status' => 'active',
                'options_prestation' => ['open-space', 'sanitaires'],
                'zones_specifiques' => ['open-space', 'salle-reunion'],
                'materiel_specifique' => ['autolaveuse'],
            ],
        ];

        foreach ($appointments as $appointment) {
            $rdv = RendezVous::updateOrCreate(
                [
                    'client_id' => $appointment['client']->id,
                    'date' => $appointment['date'],
                    'heure' => $appointment['heure'],
                ],
                $this->makeAppointmentPayload($appointment)
            );

            if ($rdv->status === 'termine') {
                Feedback::updateOrCreate(
                    ['rendez_vous_id' => $rdv->id],
                    [
                        'client_id' => $rdv->client_id,
                        'note' => 5,
                        'commentaire' => 'Prestation très professionnelle et ponctuelle.',
                        'reponse_admin' => 'Merci pour votre confiance.',
                    ]
                );
            }
        }

        $this->command?->info('✅ Données démo plateforme créées.');
    }

    protected function makeAppointmentPayload(array $appointment): array
    {
        /** @var User $client */
        $client = $appointment['client'];
        /** @var User $employee */
        $employee = $appointment['employee'];
        /** @var ServiceCatalog $service */
        $service = $appointment['service'];
        /** @var ServiceZone|null $zone */
        $zone = $appointment['zone'];
        /** @var PostalCode|null $postal */
        $postal = $appointment['postal'];
        /** @var OrganizationSite|null $site */
        $site = $appointment['site'] ?? null;

        $bookingReference = 'SS-' . now()->format('Ymd') . '-' . $appointment['reference'];
        $estimatedDuration = $service->default_duration_minutes ?: 90;
        $estimatedPrice = (float) ($appointment['devis_estime'] ?? $service->base_price ?? 0);
        $isEntreprise = $site !== null || $client->role === User::ROLE_ENTREPRISE;
        $resolvedZone = $zone ?? $site?->serviceZone;
        $resolvedPostal = $postal ?? $site?->postalCode;

        return [
            'employe_id' => $employee->id,
            'organization_account_id' => $site?->organization_account_id,
            'organization_site_id' => $site?->id,
            'service_catalog_id' => $service->id,
            'service_zone_id' => $resolvedZone?->id,
            'postal_code_id' => $resolvedPostal?->id,
            'booking_channel' => $isEntreprise ? 'entreprise_portal' : 'web',
            'booking_reference' => $bookingReference,
            'zone_snapshot' => [
                'resolution' => [
                    'status' => 'covered',
                    'message' => 'Demo seed appointment generated with structured context.',
                    'source' => $site ? 'organization_site' : 'seed_demo',
                ],
                'zone' => [
                    'id' => $resolvedZone?->id,
                    'code' => $resolvedZone?->code,
                    'name' => $resolvedZone?->name,
                    'slug' => $resolvedZone?->slug,
                    'coverage_type' => $resolvedZone?->coverage_type,
                    'status' => $resolvedZone?->status,
                    'is_bookable' => (bool) $resolvedZone?->is_bookable,
                    'is_visible' => (bool) $resolvedZone?->is_visible,
                    'travel_surcharge' => (float) ($resolvedZone?->travel_surcharge ?? 0),
                    'minimum_notice_hours' => (int) ($resolvedZone?->minimum_notice_hours ?? 0),
                    'maximum_daily_jobs' => $resolvedZone?->maximum_daily_jobs,
                    'time_buffer_minutes' => (int) ($resolvedZone?->time_buffer_minutes ?? 0),
                ],
                'postal_code' => [
                    'id' => $resolvedPostal?->id,
                    'code' => $resolvedPostal?->code,
                    'city_name' => $resolvedPostal?->city_name,
                    'province_id' => $resolvedPostal?->province_id,
                    'region_id' => $resolvedPostal?->region_id,
                    'country_id' => $resolvedPostal?->country_id,
                ],
                'organization_site' => $site ? [
                    'id' => $site->id,
                    'name' => $site->name,
                    'site_code' => $site->site_code,
                    'service_zone_id' => $site->service_zone_id,
                    'postal_code_id' => $site->postal_code_id,
                ] : null,
                'zone_id' => $resolvedZone?->id,
                'zone_name' => $resolvedZone?->name,
                'postal_code_id' => $resolvedPostal?->id,
                'postal_code_value' => $resolvedPostal?->code,
                'city_name' => $resolvedPostal?->city_name,
            ],
            'pricing_snapshot' => [
                'service' => [
                    'id' => $service->id,
                    'code' => $service->code,
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'service_identifier' => $service->code ?: $service->slug,
                    'requires_quote' => (bool) $service->requires_quote,
                    'requires_manual_validation' => (bool) $service->requires_manual_validation,
                    'is_entreprise' => (bool) $service->is_entreprise,
                    'default_duration_minutes' => (int) ($service->default_duration_minutes ?? 0),
                    'base_price' => (float) ($service->base_price ?? 0),
                ],
                'rule' => null,
                'pricing' => [
                    'estimated_price' => $estimatedPrice,
                    'estimated_duration_minutes' => $estimatedDuration,
                    'travel_surcharge' => (float) ($resolvedZone?->travel_surcharge ?? 0),
                    'applied_base_price' => (float) ($service->base_price ?? 0),
                    'applied_multiplier' => 1.0,
                ],
                'resolution' => [
                    'status' => $appointment['status'] === 'manual_validation' ? 'manual_validation' : 'covered',
                    'message' => 'Demo seed pricing snapshot.',
                    'source' => $site ? 'organization_site' : 'seed_demo',
                ],
                'requires_manual_validation' => (bool) ($service->requires_manual_validation ?? false),
                'corporate_context' => [
                    'organization_account_id' => $site?->organization_account_id,
                    'organization_site_id' => $site?->id,
                    'market' => $isEntreprise ? 'entreprise' : 'particulier',
                ],
                'service_catalog_id' => $service->id,
                'service_identifier' => $service->code ?: $service->slug,
                'service_name' => $service->name,
                'base_price' => (float) ($service->base_price ?? 0),
                'travel_surcharge' => (float) ($resolvedZone?->travel_surcharge ?? 0),
                'devis_estime' => $estimatedPrice,
                'duree_estimee' => $estimatedDuration,
            ],
            'status' => $appointment['status'],
            'duree' => $estimatedDuration,
            'duree_estimee' => $estimatedDuration,
            'devis_estime' => $estimatedPrice,
            'adresse' => $appointment['adresse'],
            'ville' => $appointment['ville'],
            'code_postal' => $appointment['code_postal'],
            'type_lieu' => $appointment['type_lieu'],
            'surface' => $appointment['surface'],
            'frequence' => $appointment['frequence'],
            'telephone_client' => $client->phone,
            'priorite' => 'normale',
            'commentaire_client' => 'Préchargé par le seed demo plateforme.',
            'options_prestation' => $appointment['options_prestation'] ?? ($site ? ['open-space'] : ['vitres']),
            'zones_specifiques' => $appointment['zones_specifiques'] ?? ($site ? ['open-space', 'salle-reunion'] : ['salon']),
            'materiel_specifique' => $appointment['materiel_specifique'] ?? ($site ? ['autolaveuse'] : []),
            'presence_animaux' => false,
            'acces_parking' => true,
            'materiel_fournit' => false,
            'is_recurrent' => (bool) ($appointment['is_recurrent'] ?? in_array($appointment['frequence'], ['hebdomadaire', 'mensuel'], true)),
            'recurrence_rule' => $appointment['recurrence_rule'] ?? null,
            'recurring_series_id' => $appointment['recurring_series_id'] ?? null,
            'recurrence_frequency' => $appointment['recurrence_frequency'] ?? null,
            'recurrence_interval' => $appointment['recurrence_interval'] ?? null,
            'recurrence_until' => $appointment['recurrence_until'] ?? null,
            'recurrence_count' => $appointment['recurrence_count'] ?? null,
            'recurrence_days' => $appointment['recurrence_days'] ?? null,
            'is_series_master' => (bool) ($appointment['is_series_master'] ?? false),
            'series_position' => $appointment['series_position'] ?? null,
            'series_status' => $appointment['series_status'] ?? null,
            'mission_finished_at' => $appointment['status'] === 'termine' ? now()->subDays(3)->setTime(11, 30) : null,
            'mission_started_at' => $appointment['status'] === 'termine' ? now()->subDays(3)->setTime(8, 30) : null,
        ];
    }
}
