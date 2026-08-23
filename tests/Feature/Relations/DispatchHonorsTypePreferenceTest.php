<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Booking\SmartDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** SP2 Task 3 — SmartDispatchService::assignBestEmployee doit honorer $booking->provider_type_preference en le passant à l'éligibilité SP1. */
class DispatchHonorsTypePreferenceTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /** Crée un prestataire PAR AILLEURS éligible : User actif rattaché à la zone via primary_service_zone_id, ProviderProfile actif+vérifié du type donné, et une disponibilité couvrant le créneau du booking. */
    private function eligibleProvider(string $type, int $zoneId, string $date, ?int $orgId = null): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $orgId,
            'provider_type' => $type,
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

    public function test_assign_best_employee_honors_independent_preference_and_excludes_company_worker(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();

        // Deux candidats PAR AILLEURS éligibles sur le MÊME créneau.
        $independent = $this->eligibleProvider(ProviderType::INDEPENDENT->value, $zoneId, $date);

        $org = OrganizationAccount::factory()->create();
        $companyWorker = $this->eligibleProvider(ProviderType::COMPANY_WORKER->value, $zoneId, $date, $org->id);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'provider_type_preference' => 'independent',
        ]);

        // Garde-fou : le worker société est PAR AILLEURS éligible (sans filtre de type,
        // 'any' le retournerait). Cela prouve que SEULE la préférence l'exclut.
        $anyEligible = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery($zoneId, 'any')->pluck('id');
        $this->assertTrue($anyEligible->contains($companyWorker->id), 'le worker société doit être éligible en mode any');
        $this->assertTrue($anyEligible->contains($independent->id));

        $best = app(SmartDispatchService::class)->assignBestEmployee($booking->fresh());

        $this->assertSame($independent->id, $best?->id);
        $this->assertNotSame($companyWorker->id, $best?->id);
    }
}
