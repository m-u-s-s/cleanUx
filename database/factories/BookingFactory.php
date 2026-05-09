<?php

namespace Database\Factories;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('+1 day', '+21 days');
        $heure = fake()->randomElement(['08:00:00', '08:30:00', '09:00:00', '09:30:00', '10:00:00', '13:30:00', '14:00:00', '14:30:00']);
        $duree = fake()->randomElement([60, 90, 120, 150]);
        $devisEstime = fake()->randomFloat(2, 69, 289);
        $serviceType = fake()->randomElement(['nettoyage_standard', 'nettoyage_profond', 'vitres', 'bureaux']);

        return [
            'service_zone_id' => fn() => ServiceZone::factory()->create([
                'coverage_type' => 'province',
                'status' => 'active',
                'is_bookable' => true,
                'is_visible' => true,
            ])->id,
            'postal_code_id' => fn() => PostalCode::factory()->create()->id,
            'service_catalog_id' => fn() => ServiceCatalog::factory()->create([
                'requires_manual_validation' => false,
                'is_entreprise' => false,
                'default_duration_minutes' => $duree,
                'base_price' => $devisEstime,
            ])->id,
            'client_id' => function (array $attributes) {
                return User::factory()->client()->create([
                    'postal_code_id' => $attributes['postal_code_id'] ?? null,
                    'primary_service_zone_id' => $attributes['service_zone_id'] ?? null,
                ])->id;
            },
            'employe_id' => function (array $attributes) {
                return User::factory()->employe()->create([
                    'postal_code_id' => $attributes['postal_code_id'] ?? null,
                    'primary_service_zone_id' => $attributes['service_zone_id'] ?? null,
                ])->id;
            },
            'organization_account_id' => null,
            'organization_site_id' => null,
            'booking_channel' => 'web',
            'booking_reference' => strtoupper('CUX-' . now()->format('Ymd') . '-' . fake()->unique()->bothify('??###??')),
            'zone_snapshot' => null,
            'pricing_snapshot' => null,
            'date' => $date->format('Y-m-d'),
            'heure' => $heure,
            'duree' => $duree,
            'duree_estimee' => $duree,
            'devis_estime' => $devisEstime,
            'motif' => fake()->sentence(),
            'status' => 'en_attente',
            'adresse' => fake()->streetAddress(),
            'ville' => fake()->city(),
            'code_postal' => fake()->numerify('####'),
            'type_lieu' => fake()->randomElement(['appartement', 'maison', 'bureaux']),
            'surface' => fake()->randomElement(['moins_50', '50_100', '100_150', '150_250']),
            'frequence' => fake()->randomElement(['ponctuel', 'hebdomadaire', 'mensuel']),
            'telephone_client' => '+32' . fake()->numerify('4########'),
            'priorite' => fake()->randomElement(['normale', 'haute']),
            'commentaire_client' => fake()->sentence(),
            'options_prestation' => ['vitres'],
            'zones_specifiques' => ['salon'],
            'materiel_specifique' => [],
            'presence_animaux' => false,
            'acces_parking' => fake()->boolean(60),
            'materiel_fournit' => false,
            'is_recurrent' => false,
            'recurrence_rule' => null,
            'recurring_series_id' => null,
            'recurrence_frequency' => null,
            'recurrence_interval' => null,
            'recurrence_until' => null,
            'recurrence_count' => null,
            'recurrence_days' => null,
            'is_series_master' => false,
            'series_position' => null,
            'series_status' => null,
            'is_favorite_slot' => false,
            'photos_reference' => [],
            'mission_started_at' => null,
            'mission_finished_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Booking $rendezVous): void {
                $this->synchronizeStructuredContext($rendezVous);
            })
            ->afterCreating(function (Booking $rendezVous): void {
                $this->synchronizeStructuredContext($rendezVous, true);
            });
    }

    public function confirme(): static
    {
        return $this->state(fn() => [
            'status' => 'confirme',
        ]);
    }

    public function refuse(): static
    {
        return $this->state(fn() => [
            'status' => 'refuse',
        ]);
    }

    public function termine(): static
    {
        return $this->state(fn() => [
            'status' => 'termine',
            'mission_started_at' => now()->subHours(2),
            'mission_finished_at' => now()->subHour(),
        ]);
    }

    public function enAttente(): static
    {
        return $this->state(fn() => [
            'status' => 'en_attente',
        ]);
    }

    public function manualValidation(): static
    {
        return $this->state(fn() => [
            'status' => 'manual_validation',
            'pricing_snapshot' => [
                'resolution' => [
                    'status' => 'manual_validation',
                    'source' => 'factory',
                ],
                'requires_manual_validation' => true,
            ],
        ]);
    }

    public function recurringSeries(?string $seriesId = null): static
    {
        $seriesId ??= (string) Str::uuid();

        return $this->state(fn() => [
            'is_recurrent' => true,
            'recurring_series_id' => $seriesId,
            'recurrence_rule' => 'FREQ=WEEKLY;INTERVAL=1',
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_until' => now()->addWeeks(8)->toDateString(),
            'recurrence_count' => 8,
            'recurrence_days' => ['monday'],
            'is_series_master' => true,
            'series_position' => 1,
            'series_status' => 'active',
        ]);
    }

    public function entreprise(): static
    {
        return $this->state(function () {
            $postalCode = PostalCode::factory()->create();
            $zone = ServiceZone::factory()->create([
                'country_id' => $postalCode->country_id,
                'region_id' => $postalCode->region_id,
                'province_id' => $postalCode->province_id,
                'coverage_type' => 'province',
            ]);

            $account = OrganizationAccount::factory()->create([
                'postal_code_id' => $postalCode->id,
                'country_id' => $postalCode->country_id,
                'region_id' => $postalCode->region_id,
                'province_id' => $postalCode->province_id,
                'city' => $postalCode->city_name,
                'postal_code' => $postalCode->code,
                'status' => 'active',
                'type' => 'entreprise',
            ]);

            $client = User::factory()->entreprise()->create([
                'organization_account_id' => $account->id,
                'postal_code_id' => $postalCode->id,
                'primary_service_zone_id' => $zone->id,
                'status' => 'active',
                'is_active' => true,
            ]);

            $site = OrganizationSite::factory()->create([
                'organization_account_id' => $account->id,
                'client_user_id' => $client->id,
                'service_zone_id' => $zone->id,
                'postal_code_id' => $postalCode->id,
                'city' => $postalCode->city_name,
                'postal_code' => $postalCode->code,
                'is_active' => true,
            ]);

            $service = ServiceCatalog::factory()->create([
                'is_entreprise' => true,
                'service_type' => 'bureaux',
                'default_duration_minutes' => 120,
                'base_price' => 199,
            ]);

            return [
                'client_id' => $client->id,
                'organization_account_id' => $account->id,
                'organization_site_id' => $site->id,
                'service_catalog_id' => $service->id,
                'service_zone_id' => $zone->id,
                'postal_code_id' => $postalCode->id,
                'booking_channel' => 'entreprise_portal',
                'type_lieu' => 'bureaux',
                'surface' => '100_150',
                'ville' => $postalCode->city_name,
                'code_postal' => $postalCode->code,
                'devis_estime' => 199,
                'duree' => 120,
                'duree_estimee' => 120,
            ];
        });
    }

    public function forStructuredContext(ServiceCatalog $catalog, ServiceZone $zone, PostalCode $postalCode): static
    {
        return $this->state(fn() => [
            'service_catalog_id' => $catalog->id,
            'service_zone_id' => $zone->id,
            'postal_code_id' => $postalCode->id,
            'ville' => $postalCode->city_name,
            'code_postal' => $postalCode->code,
            'duree' => $catalog->default_duration_minutes,
            'duree_estimee' => $catalog->default_duration_minutes,
            'devis_estime' => $catalog->base_price,
        ]);
    }

    protected function synchronizeStructuredContext(Booking $rendezVous, bool $persist = false): void
    {
        $serviceCatalog = $rendezVous->serviceCatalog ?? ($rendezVous->service_catalog_id ? ServiceCatalog::query()->find($rendezVous->service_catalog_id) : null);
        $serviceZone = $rendezVous->serviceZone ?? ($rendezVous->service_zone_id ? ServiceZone::query()->find($rendezVous->service_zone_id) : null);
        $postalCode = $rendezVous->postalCode ?? ($rendezVous->postal_code_id ? PostalCode::query()->find($rendezVous->postal_code_id) : null);
        $organizationSite = $rendezVous->organizationSite ?? ($rendezVous->organization_site_id ? OrganizationSite::query()->find($rendezVous->organization_site_id) : null);
        $organizationAccount = $rendezVous->organizationAccount ?? ($rendezVous->organization_account_id ? OrganizationAccount::query()->find($rendezVous->organization_account_id) : null);

        if ($organizationSite) {
            $rendezVous->organization_account_id ??= $organizationSite->organization_account_id;
            $rendezVous->service_zone_id ??= $organizationSite->service_zone_id;
            $rendezVous->postal_code_id ??= $organizationSite->postal_code_id;
            $serviceZone ??= $organizationSite->serviceZone;
            $postalCode ??= $organizationSite->postalCode;
        }

        if ($serviceCatalog) {
            $rendezVous->duree ??= $serviceCatalog->default_duration_minutes;
            $rendezVous->duree_estimee ??= $serviceCatalog->default_duration_minutes;
            $rendezVous->devis_estime ??= $serviceCatalog->base_price;
        }

        if ($postalCode) {
            $rendezVous->ville = $postalCode->city_name;
            $rendezVous->code_postal = $postalCode->code;
        }

        if (! $rendezVous->booking_reference) {
            $rendezVous->booking_reference = strtoupper('CUX-' . now()->format('Ymd') . '-' . fake()->unique()->bothify('##??##'));
        }

        if ($organizationAccount || $organizationSite) {
            $rendezVous->booking_channel = $rendezVous->booking_channel ?: 'entreprise_portal';
            $rendezVous->type_lieu = $rendezVous->type_lieu ?: 'bureaux';
        }

        if ($serviceZone && $postalCode && empty($rendezVous->zone_snapshot)) {
            $rendezVous->zone_snapshot = [
                'resolution' => [
                    'status' => 'factory_seeded',
                    'message' => 'Context generated by RendezVousFactory.',
                    'source' => $organizationSite ? 'organization_site' : 'factory',
                ],
                'zone' => [
                    'id' => $serviceZone->id,
                    'code' => $serviceZone->code,
                    'name' => $serviceZone->name,
                    'slug' => $serviceZone->slug,
                    'coverage_type' => $serviceZone->coverage_type,
                    'status' => $serviceZone->status,
                    'is_bookable' => (bool) $serviceZone->is_bookable,
                    'is_visible' => (bool) $serviceZone->is_visible,
                    'travel_surcharge' => (float) ($serviceZone->travel_surcharge ?? 0),
                    'minimum_notice_hours' => (int) ($serviceZone->minimum_notice_hours ?? 0),
                    'maximum_daily_jobs' => $serviceZone->maximum_daily_jobs,
                    'time_buffer_minutes' => (int) ($serviceZone->time_buffer_minutes ?? 0),
                ],
                'postal_code' => [
                    'id' => $postalCode->id,
                    'code' => $postalCode->code,
                    'city_name' => $postalCode->city_name,
                    'province_id' => $postalCode->province_id,
                    'region_id' => $postalCode->region_id,
                    'country_id' => $postalCode->country_id,
                ],
                'organization_site' => $organizationSite ? [
                    'id' => $organizationSite->id,
                    'name' => $organizationSite->name,
                    'site_code' => $organizationSite->site_code,
                    'service_zone_id' => $organizationSite->service_zone_id,
                    'postal_code_id' => $organizationSite->postal_code_id,
                ] : null,
                'zone_id' => $serviceZone->id,
                'zone_name' => $serviceZone->name,
                'postal_code_id' => $postalCode->id,
                'postal_code_value' => $postalCode->code,
                'city_name' => $postalCode->city_name,
            ];
        }

        if ($serviceCatalog && $serviceZone && empty($rendezVous->pricing_snapshot)) {
            $rendezVous->pricing_snapshot = [
                'service' => [
                    'id' => $serviceCatalog->id,
                    'code' => $serviceCatalog->code,
                    'name' => $serviceCatalog->name,
                    'slug' => $serviceCatalog->slug,
                    'service_identifier' => $serviceCatalog->code ?: $serviceCatalog->slug,
                    'requires_quote' => (bool) $serviceCatalog->requires_quote,
                    'requires_manual_validation' => (bool) $serviceCatalog->requires_manual_validation,
                    'is_entreprise' => (bool) $serviceCatalog->is_entreprise,
                    'default_duration_minutes' => (int) ($serviceCatalog->default_duration_minutes ?? 0),
                    'base_price' => (float) ($serviceCatalog->base_price ?? 0),
                ],
                'rule' => null,
                'pricing' => [
                    'estimated_price' => $rendezVous->devis_estime !== null ? (float) $rendezVous->devis_estime : null,
                    'estimated_duration_minutes' => $rendezVous->duree_estimee !== null ? (int) $rendezVous->duree_estimee : null,
                    'travel_surcharge' => (float) ($serviceZone->travel_surcharge ?? 0),
                    'applied_base_price' => (float) ($serviceCatalog->base_price ?? 0),
                    'applied_multiplier' => 1.0,
                ],
                'resolution' => [
                    'status' => $rendezVous->status === 'manual_validation' ? 'manual_validation' : 'factory_seeded',
                    'message' => 'Pricing snapshot generated by RendezVousFactory.',
                    'source' => $organizationSite ? 'organization_site' : 'factory',
                ],
                'requires_manual_validation' => $rendezVous->status === 'manual_validation' || (bool) $serviceCatalog->requires_manual_validation,
                'corporate_context' => [
                    'organization_account_id' => $organizationAccount?->id,
                    'organization_site_id' => $organizationSite?->id,
                    'market' => ($organizationAccount || $organizationSite) ? 'entreprise' : 'particulier',
                ],
                'service_catalog_id' => $serviceCatalog->id,
                'service_identifier' => $serviceCatalog->code ?: $serviceCatalog->slug,
                'service_name' => $serviceCatalog->name,
                'base_price' => (float) ($serviceCatalog->base_price ?? 0),
                'travel_surcharge' => (float) ($serviceZone->travel_surcharge ?? 0),
                'devis_estime' => $rendezVous->devis_estime !== null ? (float) $rendezVous->devis_estime : null,
                'duree_estimee' => $rendezVous->duree_estimee !== null ? (int) $rendezVous->duree_estimee : null,
            ];
        }

        if ($persist && $rendezVous->exists && $rendezVous->isDirty()) {
            $rendezVous->save();
        }
    }
}
