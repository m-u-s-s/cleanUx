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
use App\Services\Booking\SmartDispatchService;
use App\Services\Dispatch\AiDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * SP3 Task 4 — quand une réservation a $booking->assigned_provider_organization_id
 * posé (le client a choisi une société), le matcher (web SmartDispatch + mobile
 * AiDispatch) doit RESTREINDRE les candidats aux workers de cette org et
 * auto-suggérer le meilleur dispo.
 *
 * On monte deux sociétés (orgA, orgB), chacune avec UN worker company PAR AILLEURS
 * éligible (actif+vérifié, taggé du métier, rattaché à la zone, disponible). La
 * réservation cible orgA. Seul assigned_provider_organization_id doit exclure le
 * worker d'orgB — garde-fou : sans filtre org, les DEUX sont éligibles.
 */
class CompanyScopedDispatchTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Crée un worker société PAR AILLEURS éligible et le tagge du métier donné.
     */
    private function companyWorker(int $zoneId, int $orgId, int $tradeId, string $date): User
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

        $user->trades()->sync([$tradeId]);

        return $user;
    }

    public function test_dispatch_scopes_candidates_to_the_assigned_company(): void
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

        $workerA = $this->companyWorker($zoneId, $orgA->id, $trade->id, $date);
        $workerB = $this->companyWorker($zoneId, $orgB->id, $trade->id, $date);

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

        // Garde-fou anti-trivial : SANS filtre org, le matcher 'company' renvoie
        // les DEUX workers. Donc seul assigned_provider_organization_id exclut orgB.
        $bothCompany = app(EmployeeAvailabilityService::class)
            ->sortedEligibleEmployeesForZone($zoneId, 'company')
            ->pluck('id');
        $this->assertTrue($bothCompany->contains($workerA->id), 'sans filtre org: workerA éligible');
        $this->assertTrue($bothCompany->contains($workerB->id), 'sans filtre org: workerB éligible');

        // Web — SmartDispatch
        $best = app(SmartDispatchService::class)->assignBestEmployee($booking->fresh());
        $this->assertSame($orgA->id, $best?->providerProfile?->organization_account_id, 'web: doit choisir un worker de orgA');
        $this->assertNotSame($workerB->id, $best?->id);

        // Mobile — AiDispatch
        $rankedIds = app(AiDispatchService::class)->rankEmployees($booking->fresh())->pluck('employee.id');
        $this->assertTrue($rankedIds->contains($workerA->id), 'mobile: le worker de orgA est candidat');
        $this->assertFalse($rankedIds->contains($workerB->id), 'mobile: le worker de orgB est exclu');
    }
}
