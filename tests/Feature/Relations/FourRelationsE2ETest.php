<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\Matching\MatchingV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SP1 Task 7 — Capstone E2E des 4 relations C2I / C2B / B2I / B2B × multi-métiers.
 *
 * Pour chaque relation, on crée une réservation pour UN métier précis, on seede
 * UNIQUEMENT un prestataire du BON TYPE possédant ce métier dans la zone, on lance
 * le matching canonique (MatchingV2Service::bestFor) et on vérifie que le bon User
 * est choisi. Pour C2B/B2B on pousse jusqu'à la mission via MissionDispatchService::
 * accept() afin de prouver que provider_organization_id + lead_provider_user_id sont
 * correctement propagés (org du worker pour une société, null pour un indépendant).
 *
 * Convention naming :
 *   C2I = client particulier  → prestataire indépendant      (org null)
 *   C2B = client particulier  → worker société               (org = providerOrg)
 *   B2I = client société      → prestataire indépendant      (org null)
 *   B2B = client société      → worker société               (org = providerOrg)
 */
class FourRelationsE2ETest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crée Trade + ServiceZone + ServiceCatalog (lié au trade) + Booking.
     * $customerOrgId non-null => client société (B2x), null => client particulier (C2x).
     *
     * @return array{trade: Trade, zone: ServiceZone, booking: Booking}
     */
    private function seedBookingForTrade(?int $customerOrgId, ?Trade $trade = null): array
    {
        $trade ??= Trade::factory()->create(['is_active' => true]);

        $zone = ServiceZone::factory()->create([
            'coverage_type' => 'province',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
        ]);

        $catalog = ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => true,
            'requires_manual_validation' => false,
        ]);

        $booking = Booking::factory()->create([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'customer_organization_id' => $customerOrgId,
            'booking_mode' => 'scheduled',
        ]);

        return compact('trade', 'zone', 'booking');
    }

    /**
     * Crée un User + ProviderProfile actif+vérifié du type donné, rattache le métier
     * et rattache la zone (via primary_service_zone_id, suffisant pour l'éligibilité).
     */
    private function provider(string $type, Trade $trade, int $zoneId, ?int $orgId = null): User
    {
        $user = User::factory()->create([
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

        $user->trades()->syncWithoutDetaching([$trade->id]);

        return $user;
    }

    /**
     * Câble le flux jusqu'à la mission acceptée, pour vérifier la propagation d'org.
     */
    private function acceptViaMission(Booking $booking, User $worker): Mission
    {
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        $assignment = MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $worker->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'notification_sent_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        app(MissionDispatchService::class)->accept($assignment);

        return $mission->fresh();
    }

    public function test_c2i_independent_is_matched_and_org_is_null(): void
    {
        ['trade' => $trade, 'zone' => $zone, 'booking' => $booking] = $this->seedBookingForTrade(null);

        $indep = $this->provider(ProviderType::INDEPENDENT->value, $trade, $zone->id);

        $best = app(MatchingV2Service::class)->bestFor($booking);

        $this->assertNotNull($best);
        $this->assertSame($indep->id, $best->id);

        $mission = $this->acceptViaMission($booking, $best);
        $this->assertSame($indep->id, $mission->lead_provider_user_id);
        $this->assertNull($mission->provider_organization_id);
    }

    public function test_c2b_company_worker_is_matched_and_org_is_traced(): void
    {
        $providerOrg = OrganizationAccount::factory()->create();
        ['trade' => $trade, 'zone' => $zone, 'booking' => $booking] = $this->seedBookingForTrade(null);

        $worker = $this->provider(ProviderType::COMPANY_WORKER->value, $trade, $zone->id, $providerOrg->id);

        $best = app(MatchingV2Service::class)->bestFor($booking);

        $this->assertNotNull($best);
        $this->assertSame($worker->id, $best->id);

        $mission = $this->acceptViaMission($booking, $best);
        $this->assertSame($worker->id, $mission->lead_provider_user_id);
        $this->assertSame($providerOrg->id, $mission->provider_organization_id);
    }

    public function test_b2i_business_client_with_independent_is_matched_and_org_is_null(): void
    {
        $customerOrg = OrganizationAccount::factory()->create();
        ['trade' => $trade, 'zone' => $zone, 'booking' => $booking] = $this->seedBookingForTrade($customerOrg->id);

        $this->assertSame($customerOrg->id, $booking->fresh()->customer_organization_id);

        $indep = $this->provider(ProviderType::INDEPENDENT->value, $trade, $zone->id);

        $best = app(MatchingV2Service::class)->bestFor($booking);

        $this->assertNotNull($best);
        $this->assertSame($indep->id, $best->id);

        $mission = $this->acceptViaMission($booking, $best);
        $this->assertSame($indep->id, $mission->lead_provider_user_id);
        $this->assertNull($mission->provider_organization_id);
    }

    public function test_b2b_business_client_with_company_worker_is_matched_and_org_is_traced(): void
    {
        $customerOrg = OrganizationAccount::factory()->create();
        $providerOrg = OrganizationAccount::factory()->create();
        ['trade' => $trade, 'zone' => $zone, 'booking' => $booking] = $this->seedBookingForTrade($customerOrg->id);

        $this->assertSame($customerOrg->id, $booking->fresh()->customer_organization_id);

        $worker = $this->provider(ProviderType::COMPANY_WORKER->value, $trade, $zone->id, $providerOrg->id);

        $best = app(MatchingV2Service::class)->bestFor($booking);

        $this->assertNotNull($best);
        $this->assertSame($worker->id, $best->id);

        $mission = $this->acceptViaMission($booking, $best);
        $this->assertSame($worker->id, $mission->lead_provider_user_id);
        // L'org du worker (providerOrg), PAS l'org cliente (customerOrg).
        $this->assertSame($providerOrg->id, $mission->provider_organization_id);
        $this->assertNotSame($customerOrg->id, $mission->provider_organization_id);
    }

    public function test_negatif_metier_only_tagged_provider_is_chosen(): void
    {
        // ≥2 candidats dans la zone, UN SEUL taggé pour le métier demandé, pour que
        // applyTradeFilter s'applique réellement (et ne tombe pas en fallback ouvert).
        ['trade' => $trade, 'zone' => $zone, 'booking' => $booking] = $this->seedBookingForTrade(null);
        $otherTrade = Trade::factory()->create(['is_active' => true]);

        $tagged = $this->provider(ProviderType::INDEPENDENT->value, $trade, $zone->id);
        $wrongTrade = $this->provider(ProviderType::INDEPENDENT->value, $otherTrade, $zone->id);

        $best = app(MatchingV2Service::class)->bestFor($booking);

        $this->assertNotNull($best);
        $this->assertSame($tagged->id, $best->id, 'le matcher doit choisir le presta possédant le métier demandé');
        $this->assertNotSame($wrongTrade->id, $best->id);

        // Et le candidat hors-métier doit être exclu de la liste classée.
        $rankedIds = app(MatchingV2Service::class)->rankCandidates($booking)
            ->map(fn ($r) => $r['employee']->id);
        $this->assertTrue($rankedIds->contains($tagged->id));
        $this->assertFalse($rankedIds->contains($wrongTrade->id));
    }

    public function test_negatif_type_eligibility_filters_by_provider_type(): void
    {
        ['trade' => $trade, 'zone' => $zone] = $this->seedBookingForTrade(null);
        $providerOrg = OrganizationAccount::factory()->create();

        $companyWorker = $this->provider(ProviderType::COMPANY_WORKER->value, $trade, $zone->id, $providerOrg->id);
        $indep = $this->provider(ProviderType::INDEPENDENT->value, $trade, $zone->id);

        $availability = app(EmployeeAvailabilityService::class);

        $independentIds = $availability->eligibleEmployeesQuery($zone->id, 'independent')->pluck('id');
        $this->assertTrue($independentIds->contains($indep->id));
        $this->assertFalse(
            $independentIds->contains($companyWorker->id),
            'un worker société ne doit PAS être éligible quand on filtre sur independent'
        );

        $companyIds = $availability->eligibleEmployeesQuery($zone->id, 'company')->pluck('id');
        $this->assertTrue($companyIds->contains($companyWorker->id));
        $this->assertFalse(
            $companyIds->contains($indep->id),
            'un indépendant ne doit PAS être éligible quand on filtre sur company'
        );
    }
}
