<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOnlyExistingColumns;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoPlatformSeeder extends Seeder
{
    use SeedsOnlyExistingColumns;

    public function run(): void
    {
        if (! Schema::hasTable('users')) {
            $this->command?->warn('⚠️ Table users absente, DemoPlatformSeeder ignoré.');
            return;
        }

        $admin = $this->seedUser('admin@cleanux.test', 'Admin CleanUx', 'admin', '+3225550000', 'admin');
        $companyContact = $this->seedUser('facilities@atlasfacilities.test', 'Atlas Facilities Manager', 'user', '+32471111222', 'entreprise');
        $premiumClient = $this->seedUser('premium.client@cleanux.test', 'Client Premium Bruxelles', 'user', '+32470000111', 'client');
        $standardClient = $this->seedUser('client.standard@cleanux.test', 'Client Standard Gand', 'user', '+32470000999', 'client');

        $brusselsProvider = $this->seedUser('bruxelles.team@cleanux.test', 'Prestataire Bruxelles', 'user', '+32461111111', 'employe');
        $gandProvider = $this->seedUser('gand.team@cleanux.test', 'Prestataire Gand', 'user', '+32462222222', 'employe');
        $anversProvider = $this->seedUser('anvers.team@cleanux.test', 'Prestataire Anvers', 'user', '+32463333333', 'employe');

        $this->seedCustomerProfile($premiumClient?->id, 'personal', 'premium', 'active', [
            'default_city' => 'Ixelles',
            'default_postal_code' => '1050',
            'default_phone' => '+32470000111',
        ]);
        $this->seedCustomerProfile($standardClient?->id, 'personal', 'standard', 'inactive', [
            'default_city' => 'Gand',
            'default_postal_code' => '9000',
            'default_phone' => '+32470000999',
        ]);
        $this->seedCustomerProfile($companyContact?->id, 'company', 'business', 'active', [
            'default_city' => 'Bruxelles',
            'default_postal_code' => '1000',
            'default_phone' => '+32471111222',
        ]);

        foreach ([$brusselsProvider, $gandProvider, $anversProvider] as $provider) {
            $this->seedProviderProfile($provider?->id);
        }

        $clientOrg = $this->seedOrganization('Atlas Facilities Belgium', 'client_company', [
            'legal_name' => 'Atlas Facilities Belgium SA',
            'tva_number' => 'BE0123456789',
            'email' => 'ops@atlasfacilities.test',
            'billing_email' => 'finance@atlasfacilities.test',
            'phone' => '+3225550101',
            'billing_city' => 'Bruxelles',
            'billing_postal_code' => '1000',
            'billing_address' => 'Rue de l\'Industrie 12',
        ]);

        $providerOrg = $this->seedOrganization('CleanUx Partner Brussels', 'provider_company', [
            'legal_name' => 'CleanUx Partner Brussels SRL',
            'tva_number' => 'BE0987654321',
            'email' => 'team@cleanuxpartner.test',
            'phone' => '+3225550199',
            'billing_city' => 'Bruxelles',
            'billing_postal_code' => '1000',
            'billing_address' => 'Avenue Louise 120',
        ]);

        $this->seedOrganizationMember($clientOrg?->id, $companyContact?->id, 'owner');
        $this->seedOrganizationMember($providerOrg?->id, $brusselsProvider?->id, 'owner');
        $this->seedOrganizationMember($providerOrg?->id, $gandProvider?->id, 'worker');
        $this->seedOrganizationMember($providerOrg?->id, $anversProvider?->id, 'worker');

        // Rattache l'utilisateur entreprise à son compte pour satisfaire les
        // contrôles de readiness (entreprise_users_without_account).
        if ($companyContact && $clientOrg && \Illuminate\Support\Facades\Schema::hasColumn('users', 'organization_account_id')) {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $companyContact->id)
                ->update(['organization_account_id' => $clientOrg->id]);
        }

        $brusselsZone = $this->firstZone(['zone-bruxelles', 'belgique-couverture-nationale']);
        $gandZone = $this->firstZone(['zone-gand', 'belgique-couverture-nationale']);

        $hqSite = $this->seedSite($clientOrg?->id, 'Siège Bruxelles', [
            'type' => 'office',
            'address' => 'Rue de l\'Industrie 12',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'country' => 'BE',
            'surface_m2' => 450,
            'service_zone_id' => $brusselsZone?->id,
            'contact_name' => 'Atlas Front Desk',
            'contact_phone' => '+3225550102',
            'contact_email' => 'hq@atlasfacilities.test',
            'status' => 'active',
            'metadata' => ['site_code' => 'BRU-HQ', 'seeded' => true],
        ]);

        $gandSite = $this->seedSite($clientOrg?->id, 'Hub Gand', [
            'type' => 'office',
            'address' => 'Kouter 18',
            'city' => 'Gand',
            'postal_code' => '9000',
            'country' => 'BE',
            'surface_m2' => 280,
            'service_zone_id' => $gandZone?->id,
            'contact_name' => 'Atlas Ops Gent',
            'contact_phone' => '+3295550102',
            'contact_email' => 'gent@atlasfacilities.test',
            'status' => 'active',
            'metadata' => ['site_code' => 'GNT-OPS', 'seeded' => true],
        ]);

        // Assigne les employés aux zones (satisfait employees_without_active_zone_assignment).
        $anversZone = $this->firstZone(['zone-anvers', 'belgique-couverture-nationale']) ?? $brusselsZone;
        foreach (
            [
                [$brusselsProvider?->id, $brusselsZone?->id],
                [$gandProvider?->id, $gandZone?->id],
                [$anversProvider?->id, $anversZone?->id],
            ] as [$userId, $zoneId]
        ) {
            if (! $userId || ! $zoneId) {
                continue;
            }
            $this->updateOrInsertTable('employee_zone_assignments', [
                'user_id' => $userId,
                'service_zone_id' => $zoneId,
            ], [
                'user_id' => $userId,
                'service_zone_id' => $zoneId,
                'assignment_type' => 'primary',
                'coverage_priority' => 100,
                'is_active' => true,
                'starts_at' => now(),
            ]);
        }

        $team = $this->seedProviderTeam($providerOrg?->id, 'Équipe Bruxelles', $brusselsProvider?->id, $brusselsZone?->id);
        $this->seedProviderTeamMember($team?->id, $brusselsProvider?->id, 'lead');
        $this->seedProviderTeamMember($team?->id, $gandProvider?->id, 'member');

        foreach ([$brusselsProvider, $gandProvider, $anversProvider] as $provider) {
            $this->seedAvailability($provider?->id, $providerOrg?->id);
        }

        $this->seedFavorite($premiumClient?->id, $brusselsProvider?->id);
        $this->seedFavorite($premiumClient?->id, $anversProvider?->id);

        $standardService = $this->serviceByCode('NETTOYAGE_STANDARD');
        $deepService = $this->serviceByCode('NETTOYAGE_PROFOND');
        $officeService = $this->serviceByCode('BUREAUX');

        $this->seedBooking('DEMO-BRU-001', $premiumClient, $brusselsProvider, $deepService, $brusselsZone, null, [
            'date' => now()->addDays(2)->toDateString(),
            'time' => '09:00:00',
            'address' => 'Avenue Louise 120',
            'city' => 'Ixelles',
            'postal_code' => '1050',
            'place_type' => 'appartement',
            'frequency' => 'mensuel',
            'status' => 'confirmed',
            'legacy_status' => 'confirme',
            'price' => 149,
        ]);

        $this->seedBooking('DEMO-GNT-001', $standardClient, $gandProvider, $standardService, $gandZone, null, [
            'date' => now()->addDay()->toDateString(),
            'time' => '14:00:00',
            'address' => 'Kortrijksesteenweg 45',
            'city' => 'Gand',
            'postal_code' => '9000',
            'place_type' => 'maison',
            'frequency' => 'ponctuel',
            'status' => 'confirmed',
            'legacy_status' => 'confirme',
            'price' => 79,
        ]);

        $finishedBooking = $this->seedBooking('DEMO-ENT-BRU-001', $companyContact, $brusselsProvider, $officeService, $brusselsZone, $hqSite, [
            'date' => now()->subDays(3)->toDateString(),
            'time' => '08:30:00',
            'address' => 'Rue de l\'Industrie 12',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'place_type' => 'bureaux',
            'frequency' => 'hebdomadaire',
            'status' => 'completed',
            'legacy_status' => 'termine',
            'price' => 219,
            'customer_organization_id' => $clientOrg?->id,
        ]);

        if ($finishedBooking) {
            $this->seedFeedback($finishedBooking->id, $companyContact?->id, $clientOrg?->id);
        }

        $anversZone = $this->firstZone(['zone-anvers', 'belgique-couverture-nationale']) ?? $brusselsZone;
        $this->seedBooking('DEMO-ANV-001', $premiumClient, $anversProvider, $standardService, $anversZone, null, [
            'date' => now()->addDays(5)->toDateString(),
            'time' => '11:00:00',
            'address' => 'Meir 1',
            'city' => 'Anvers',
            'postal_code' => '2000',
            'place_type' => 'maison',
            'frequency' => 'mensuel',
            'status' => 'en_attente',
            'legacy_status' => 'en_attente',
            'price' => 99,
        ]);

        $this->command?->info('✅ Données démo plateforme créées avec colonnes compatibles migrations.');
    }

    protected function seedUser(string $email, string $name, string $platformRole, string $phone, ?string $role = null): ?object
    {
        return $this->updateOrInsertTable('users', ['email' => $email], [
            'name' => $name,
            'password' => Hash::make('password'),
            'phone' => $phone,
            'role' => $role ?? ($platformRole === 'admin' ? 'admin' : 'client'),
            'platform_role' => $platformRole,
            'status' => 'active',
            'is_active' => true,
            'locale' => 'fr_BE',
            'timezone' => 'Europe/Brussels',
            'email_verified_at' => now(),
        ]);
    }

    protected function seedCustomerProfile(?int $userId, string $type, string $plan, string $planStatus, array $extra = []): void
    {
        if (! $userId) {
            return;
        }

        $this->updateOrInsertTable('customer_profiles', ['user_id' => $userId], [
            'customer_type' => $type,
            'plan_type' => $plan,
            'plan_status' => $planStatus,
            'premium_started_at' => $plan === 'premium' ? now()->subMonth() : null,
            'premium_renewal_at' => $plan === 'premium' ? now()->addMonth() : null,
            'preferences' => ['seeded' => true],
            ...$extra,
        ]);
    }

    protected function seedProviderProfile(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        $this->updateOrInsertTable('provider_profiles', ['user_id' => $userId], [
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'skills' => ['nettoyage', 'vitres', 'bureaux'],
            'hourly_rate' => 28,
            'commission_rate' => 20,
            'default_slot_duration' => 30,
            'settings' => ['accepts_demo_jobs' => true],
        ]);
    }

    protected function seedOrganization(string $name, string $type, array $extra = []): ?object
    {
        return $this->updateOrInsertTable('organization_accounts', ['name' => $name], [
            'legal_name' => $extra['legal_name'] ?? $name,
            'slug' => Str::slug($name),
            'type' => $type,
            'tva_number' => $extra['tva_number'] ?? null,
            'email' => $extra['email'] ?? null,
            'billing_email' => $extra['billing_email'] ?? $extra['email'] ?? null,
            'phone' => $extra['phone'] ?? null,
            'billing_address' => $extra['billing_address'] ?? null,
            'billing_city' => $extra['billing_city'] ?? null,
            'billing_postal_code' => $extra['billing_postal_code'] ?? null,
            'billing_country' => 'BE',
            'default_currency' => 'EUR',
            'payment_terms' => '30_days',
            'status' => 'active',
            'is_multisite' => true,
            'requires_internal_approval' => $type === 'client_company',
            'metadata' => ['seeded' => true],
        ]);
    }

    protected function seedOrganizationMember(?int $organizationId, ?int $userId, string $role): void
    {
        if (! $organizationId || ! $userId) {
            return;
        }

        $this->updateOrInsertTable('organization_members', [
            'organization_account_id' => $organizationId,
            'user_id' => $userId,
        ], [
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
            'permissions' => [],
        ]);
    }

    protected function seedSite(?int $organizationId, string $name, array $payload): ?object
    {
        if (! $organizationId) {
            return null;
        }

        if (empty($payload['postal_code_id']) && ! empty($payload['postal_code']) && \Illuminate\Support\Facades\Schema::hasTable('postal_codes')) {
            $payload['postal_code_id'] = \Illuminate\Support\Facades\DB::table('postal_codes')
                ->where('code', $payload['postal_code'])
                ->value('id');
        }

        return $this->updateOrInsertTable('organization_sites', [
            'organization_account_id' => $organizationId,
            'name' => $name,
        ], [
            'name' => $name,
            ...$payload,
        ]);
    }

    protected function seedProviderTeam(?int $organizationId, string $name, ?int $leadId, ?int $zoneId): ?object
    {
        if (! $organizationId) {
            return null;
        }

        return $this->updateOrInsertTable('provider_teams', [
            'organization_account_id' => $organizationId,
            'name' => $name,
        ], [
            'team_lead_id' => $leadId,
            'service_zone_id' => $zoneId,
            'description' => 'Équipe démo seedée automatiquement.',
            'status' => 'active',
            'settings' => ['seeded' => true],
        ]);
    }

    protected function seedProviderTeamMember(?int $teamId, ?int $userId, string $role): void
    {
        if (! $teamId || ! $userId) {
            return;
        }

        $this->updateOrInsertTable('provider_team_members', [
            'provider_team_id' => $teamId,
            'user_id' => $userId,
        ], [
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function seedAvailability(?int $userId, ?int $organizationId): void
    {
        if (! $userId || ! $this->hasTable('provider_availabilities')) {
            return;
        }

        foreach (range(0, 6) as $day) {
            $date = now()->startOfWeek()->addDays($day)->toDateString();

            foreach ([['09:00:00', '12:00:00'], ['14:00:00', '17:00:00']] as [$start, $end]) {
                $this->updateOrInsertTable('provider_availabilities', [
                    'user_id' => $userId,
                    'date' => $date,
                    'start_time' => $start,
                ], [
                    'organization_account_id' => $organizationId,
                    'end_time' => $end,
                    'weekday' => now()->startOfWeek()->addDays($day)->dayOfWeekIso,
                    'type' => 'available',
                    'is_available' => true,
                    'metadata' => ['seeded' => true],
                ]);
            }
        }
    }

    protected function seedFavorite(?int $customerId, ?int $providerId): void
    {
        if (! $customerId || ! $providerId) {
            return;
        }

        $this->updateOrInsertTable('provider_favorites', [
            'customer_user_id' => $customerId,
            'provider_user_id' => $providerId,
        ], [
            'status' => 'active',
            'notes' => 'Favori démo',
        ]);
    }

    protected function seedBooking(string $reference, ?object $client, ?object $provider, ?object $service, ?object $zone, ?object $site, array $payload): ?object
    {
        if (! $client || ! $service || ! $this->hasTable('bookings')) {
            return null;
        }

        $duration = (int) ($service->default_duration_minutes ?? 90);
        $price = (float) ($payload['price'] ?? $service->base_price ?? 0);

        $postalCodeId = null;
        if (! empty($payload['postal_code']) && \Illuminate\Support\Facades\Schema::hasTable('postal_codes')) {
            $postalCodeId = \Illuminate\Support\Facades\DB::table('postal_codes')
                ->where('code', $payload['postal_code'])
                ->value('id');
        }

        return $this->updateOrInsertTable('bookings', ['booking_reference' => $reference], [
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'customer_organization_id' => $payload['customer_organization_id'] ?? null,
            'organization_site_id' => $site?->id,
            'service_catalog_id' => $service->id,
            'service_zone_id' => $zone?->id,
            'postal_code_id' => $postalCodeId,
            'assigned_provider_user_id' => $provider?->id,
            'employe_id' => $provider?->id,
            'scheduled_date' => $payload['date'],
            'scheduled_time' => $payload['time'],
            'date' => $payload['date'],
            'heure' => $payload['time'],
            'booking_mode' => 'scheduled',
            'status' => $payload['status'],
            'statut' => $payload['legacy_status'] ?? $payload['status'],
            'priority' => 'normal',
            'place_type' => $payload['place_type'] ?? null,
            'frequency' => $payload['frequency'] ?? null,
            'address' => $payload['address'],
            'adresse' => $payload['address'],
            'city' => $payload['city'],
            'ville' => $payload['city'],
            'postal_code' => $payload['postal_code'],
            'code_postal' => $payload['postal_code'],
            'country' => 'BE',
            'contact_name' => $client->name,
            'contact_phone' => $client->phone ?? null,
            'telephone_client' => $client->phone ?? null,
            'contact_email' => $client->email,
            'customer_comment' => 'Rendez-vous démo seedé.',
            'commentaire_client' => 'Rendez-vous démo seedé.',
            'estimated_price' => $price,
            'devis_estime' => $price,
            'estimated_duration_minutes' => $duration,
            'duree_estimee' => $duration,
            'currency' => 'EUR',
            'options' => ['seeded' => true],
            'areas' => ['demo'],
            'pricing_snapshot' => [
                'service_catalog_id' => $service->id,
                'service_name' => $service->name,
                'estimated_price' => $price,
                'estimated_duration_minutes' => $duration,
                'source' => 'DemoPlatformSeeder',
            ],
            'zone_snapshot' => [
                'zone_id' => $zone?->id,
                'zone_name' => $zone?->name,
                'source' => 'DemoPlatformSeeder',
            ],
        ]);
    }

    protected function seedFeedback(int $bookingId, ?int $clientId, ?int $organizationId): void
    {
        $table = \Illuminate\Support\Facades\Schema::hasTable('feedback')
            ? 'feedback'
            : (\Illuminate\Support\Facades\Schema::hasTable('feedbacks') ? 'feedbacks' : null);

        if (! $table) {
            return;
        }

        $this->updateOrInsertTable($table, ['rendez_vous_id' => $bookingId], [
            'booking_id' => $bookingId,
            'client_id' => $clientId,
            'client_user_id' => $clientId,
            'client_organization_id' => $organizationId,
            'note' => 5,
            'rating' => 5,
            'commentaire' => 'Prestation très professionnelle et ponctuelle.',
            'feedback' => 'Prestation très professionnelle et ponctuelle.',
            'reponse_admin' => 'Merci pour votre confiance.',
            'status' => 'published',
            'metadata' => ['seeded' => true],
        ]);
    }

    protected function firstZone(array $slugs): ?object
    {
        if (! $this->hasTable('service_zones')) {
            return null;
        }

        return DB::table('service_zones')->whereIn('slug', $slugs)->first();
    }

    protected function serviceByCode(string $code): ?object
    {
        if (! $this->hasTable('service_catalogs')) {
            return null;
        }

        return DB::table('service_catalogs')->where('code', $code)->first();
    }
}
