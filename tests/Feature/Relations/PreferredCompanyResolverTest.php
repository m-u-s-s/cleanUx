<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Booking\PreferredCompanyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** SP3 Task 5 — PreferredCompanyResolver (parité SP2 PreferredProviderResolver, mais au niveau SOCIÉTÉ). */
class PreferredCompanyResolverTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /** Crée un worker société PAR AILLEURS éligible : User actif rattaché à la zone, ProviderProfile company_worker actif+vérifié rattaché à l'org, disponibilité couvrant le créneau (08:00-18:00). */
    private function companyWorker(int $zoneId, int $orgId, string $date): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $orgId,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        Disponibilite::create([
            'user_id' => $user->id,
            'date' => $date,
            'heure_debut' => '08:00:00',
            'heure_fin' => '18:00:00',
        ]);

        return $user;
    }

    private function makeBooking(int $zoneId, string $date, int $orgId): Booking
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        return Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'assigned_provider_organization_id' => $orgId,
        ]);
    }

    public function test_company_with_available_worker_is_assignable(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $orgA = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);

        // orgA a un worker company éligible + dispo sur le créneau 10:00.
        $worker = $this->companyWorker($zoneId, $orgA->id, $date);

        $booking = $this->makeBooking($zoneId, $date, $orgA->id);

        $r = app(PreferredCompanyResolver::class)->resolve($booking->fresh());

        $this->assertSame('assigned', $r['status']);
        $this->assertSame($worker->id, $r['provider']?->id);
        $this->assertSame($orgA->id, $r['provider']?->providerProfile?->organization_account_id);
        $this->assertSame([], $r['alternative_slots']);
    }

    public function test_company_without_available_worker_returns_alternative_slots(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        $orgA = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);

        // Le worker de orgA est dispo en théorie (08:00-18:00)...
        $worker = $this->companyWorker($zoneId, $orgA->id, $date);

        // ...mais OCCUPÉ par un RDV concurrent confirmé chevauchant le créneau 10:00.
        $otherClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        Booking::factory()->create([
            'client_id' => $otherClient->id,
            'employe_id' => $worker->id,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'confirme',
        ]);

        $booking = $this->makeBooking($zoneId, $date, $orgA->id);

        $r = app(PreferredCompanyResolver::class)->resolve($booking->fresh());

        $this->assertSame('unavailable', $r['status']);
        $this->assertNotEmpty($r['alternative_slots']);
    }
}
