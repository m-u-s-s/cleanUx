<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Matching\MatchingV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * MatchingV2 est le matcher PRIMAIRE (MATCHING_V2_ENABLED=true par défaut, utilisé
 * par AiDispatchService::bestEmployeeFor). Il doit donc HONORER le choix du client :
 *   - provider_type_preference (SP2) : indépendant vs société ;
 *   - assigned_provider_organization_id (SP3) : la société choisie.
 *
 * Chaque candidat « exclu » est PAR AILLEURS éligible (actif+vérifié, taggé du métier,
 * rattaché à la zone, disponible) — seul le type/l'org doit l'exclure. Garde-fou
 * anti-trivial : sans préférence/org ('any'/null), MatchingV2 verrait les deux.
 */
class MatchingV2HonorsSelectionTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Crée un prestataire PAR AILLEURS éligible et le tagge du métier donné.
     */
    private function eligibleProvider(string $type, int $zoneId, int $tradeId, string $date, ?int $orgId = null): User
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

        $user->trades()->sync([$tradeId]);

        return $user;
    }

    public function test_matching_v2_honors_provider_type_preference(): void
    {
        $trade = Trade::factory()->create();

        $context = $this->createCoverageContext([
            'service' => ['trade_id' => $trade->id],
        ]);
        $zoneId = $context['zone']->id;
        $catalog = $context['service'];
        $date = now()->addDays(3)->toDateString();

        $independent = $this->eligibleProvider(ProviderType::INDEPENDENT->value, $zoneId, $trade->id, $date);

        $org = OrganizationAccount::factory()->create();
        $companyWorker = $this->eligibleProvider(ProviderType::COMPANY_WORKER->value, $zoneId, $trade->id, $date, $org->id);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_catalog_id' => $catalog->id,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'provider_type_preference' => 'independent',
        ]);

        // Garde-fou anti-trivial : SANS préférence, les DEUX sont éligibles. Donc
        // seule provider_type_preference doit exclure le worker société.
        $anyEligible = app(EmployeeAvailabilityService::class)
            ->sortedEligibleEmployeesForZone($zoneId, 'any')
            ->pluck('id');
        $this->assertTrue($anyEligible->contains($independent->id));
        $this->assertTrue($anyEligible->contains($companyWorker->id), 'sans préférence: le worker société est éligible');

        $best = app(MatchingV2Service::class)->bestFor($booking->fresh());

        $this->assertSame($independent->id, $best?->id);
        $this->assertNotSame($companyWorker->id, $best?->id);
    }

    public function test_matching_v2_honors_assigned_company(): void
    {
        $trade = Trade::factory()->create();

        $context = $this->createCoverageContext([
            'service' => ['trade_id' => $trade->id],
        ]);
        $zoneId = $context['zone']->id;
        $catalog = $context['service'];
        $date = now()->addDays(3)->toDateString();

        $orgA = OrganizationAccount::factory()->create();
        $orgB = OrganizationAccount::factory()->create();

        $workerA = $this->eligibleProvider(ProviderType::COMPANY_WORKER->value, $zoneId, $trade->id, $date, $orgA->id);
        $workerB = $this->eligibleProvider(ProviderType::COMPANY_WORKER->value, $zoneId, $trade->id, $date, $orgB->id);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_catalog_id' => $catalog->id,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
            'provider_type_preference' => 'company',
            'assigned_provider_organization_id' => $orgA->id,
        ]);

        // Garde-fou anti-trivial : SANS filtre org, le matcher 'company' renvoie les
        // DEUX workers. Donc seul assigned_provider_organization_id exclut orgB.
        $bothCompany = app(EmployeeAvailabilityService::class)
            ->sortedEligibleEmployeesForZone($zoneId, 'company')
            ->pluck('id');
        $this->assertTrue($bothCompany->contains($workerA->id), 'sans filtre org: workerA éligible');
        $this->assertTrue($bothCompany->contains($workerB->id), 'sans filtre org: workerB éligible');

        $best = app(MatchingV2Service::class)->bestFor($booking->fresh());

        $this->assertSame($orgA->id, $best?->providerProfile?->organization_account_id, 'doit choisir un worker de orgA');
        $this->assertNotSame($workerB->id, $best?->id);
    }
}
